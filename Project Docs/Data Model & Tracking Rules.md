# Data Model & Tracking Rules

Purpose: provide unambiguous developer-facing rules, DB mappings and algorithms so the FA Sanity Check module can be implemented without assumptions.

1) Canonical FA link chain
- Inbound (purchase) chain: PO -> GRN/Delivery -> Supplier Invoice -> Supplier Payment. Each of these document lines in standard FA 2.3.X contains `stock_id` on the line item and should be followed as canonical inbound linkage.
- Outbound (sales) chain: Sales Order / Sales Invoice -> Customer Payment. Sales invoice lines contain `stock_id`.
- Ledger/settlement: `bank_trans` / `gl_trans` / `bank_deposits` records contain deposits and transfer lines, including processor fee lines.

2) Core tables (developer note: use FA 2.3.X table names in the codebase; below are logical references)
- `purch_lines` / `purch_orders` (PO lines)
- `grn_items` / `grn_batch` (goods received / delivery lines)
- `supp_trans` (supplier invoices/credits)
- `supp_allocations` (supplier invoice <-> payment allocations)
- `stock_moves` (inventory receipt / issue / transfer records)
- `debtor_trans` (sales invoices)
- `cust_allocations` (customer invoice <-> payment allocations)
- `bank_trans` (bank entries including transfers and fees)
- `gl_trans` (general ledger lines if needed for fee detection)
- `stock_master` (item master with `stock_id`) and `locations` (warehouse/location id)

3) Trace record / view design
Create a read-only view or materialized table `item_trace` with one row per traced movement instance. Suggested columns:
- `trace_id` (PK)
- `stock_id`
- `location_id`
- `serial_id` (nullable) — used when serial/serial-module present
- `in_txn_type` (PO/GRN/SupplierInvoice)
- `in_txn_no` (document identifier)
- `in_txn_date` (transaction_date)
- `in_qty` / `in_unit_cost` / `in_extended_cost` (include allocated taxes & shipping as allocated cost fields)
- `out_txn_type` (SalesInvoice/Return/Adjustment)
- `out_txn_no`
- `out_txn_date`
- `out_qty` / `out_unit_price` / `out_extended_value`
- `payments` (JSON array listing payment_id, account_id, date, amount)
- `processor_transfers` (JSON array: transfer_id, from_account, to_account, date, amount, fee_amount)
- `final_deposit_id` (bank_trans id when funds reached `final_cash_account`)
- `shipping_allocated` / `tax_allocated`
- `discounts` (invoice vs payment discounts separated)
- `anomaly_flags` (enum list)
- `severity` (RED/YELLOW/NONE)
- `computed_fifo_batch_id` (for internal debugging)

4) FIFO per location algorithm (high-level)
- Step 1: Gather all inbound `stock_moves` receipts for `stock_id` filtered by `location_id` and ordered by `transaction_date` ASC (authoritative).
- Step 2: Gather all outbound `stock_moves` issues for that `location_id` ordered by `transaction_date` ASC.
- Step 3: Consume inbound receipts in order when assigning outbound quantities (FIFO). If a transfer record moves qty between locations, treat the transfer as an outbound for source location and inbound for destination (respecting transfer `transaction_date`).
- Step 4: Attach PO/GRN/supplier_invoice identifiers to the inbound batch being consumed so the outbound line shows originating PO/GRN/SupplierInvoice references.
- Note: if `serial_id` exists for the item/line, bypass FIFO and match by `serial_id` equality. When serial module becomes available, prefer serial matching.

5) Shipping & tax allocation rules
- Default: allocate supplier shipping & per-invoice taxes proportionally to line value on the supplier invoice.
- Config: `shipping_allocation_mode` = `proportional_by_value` (default) or `use_dimensions` (volumetric weight) — if switched, use `stock_master` dimensions/weight fields (when product-dimensions module present) to compute proportion.
- Result: populate `in_unit_cost` and `in_extended_cost` with allocated share so trace shows landed cost per unit.

6) Payment & settlement tracing rules
- Use allocation tables to match invoices to payments: `supp_allocations` for suppliers, `cust_allocations` for customers. List all payments that allocate to an invoice in `payments` JSON.
- If a payment's `bank_trans.account_id` is in `final_cash_accounts` (admin-configured), mark as final. If in `processor_accounts`, mark as processor-hold and search for subsequent `bank_trans` transfers from that processor account to any `final_cash_account` within a configurable date-window (default 30 days).
- When following transfers, include any `bank_trans` or `gl_trans` lines on the deposit that represent processor fees (negative lines or separate fee GL lines). Use configured `processor_accounts` or description pattern matching to detect these lines.
- For split payments (multiple allocations to an invoice), list all payments with amounts and dates — do not prorate to item lines. If sum(payments) != invoice_total within `anomaly_tolerances`, flag invoice with `anomaly_flags`.

7) Special cases & heuristics
- Minimal-value payments (e.g., 0.01): include them and flag them if they cause mismatches.
- Returns / Credit Notes: trace as separate outbound-to-inbound reversal; ensure return date does not predate the original sale — if it does, flag.
- Future-dated transactions: if `transaction_date` > today or `out_txn_date` < `in_txn_date`, set `anomaly_flags`.

8) Anomaly detection & severity rules (configurable)
- RED (critical): sale dated before any inbound record for same `stock_id` at same `location_id`; payment never reaches a `final_cash_account` within configured window; negative quantities unexplainable by returns.
- YELLOW (warning): invoice payment mismatch beyond configured tolerance (currency or %); minimal 0.01 payments present; shipping/tax allocation ambiguous (missing lines) requiring manual review.
- Config: `anomaly_tolerances` includes `currency_tolerance` (e.g., 0.05) and `percent_tolerance` (e.g., 0.5%).

9) Example pseudocode for trace view (SQL-like, simplified)
-- Build inbound receipts per stock/location
WITH inbound AS (
  SELECT stock_id, location_id, qty, cost, transaction_date, doc_type, doc_no
  FROM stock_moves
  WHERE move_type = 'receipt'
),
outbound AS (
  SELECT stock_id, location_id, qty, transaction_date, doc_type, doc_no
  FROM stock_moves
  WHERE move_type IN ('issue','sale','transfer_out')
)
-- apply FIFO per location: iterative consumption (implement in procedural SQL or app layer)

10) Developer guidance & integration points
- Implement core FIFO consumption in the app layer or via a stored procedure — avoid complex single-pass SQL when inventory volume is large; prefer a deterministic procedural approach that returns batch assignments.
- Provide a single read-only API endpoint `GET /item-trace?stock_id=XXX&location=YYY` returning `item_trace` rows plus `anomaly_flags` and `severity`.
- All configuration must be stored in a small config table: `sanity_config` with keys: `final_cash_accounts` (JSON array), `processor_accounts` (JSON array), `shipping_allocation_mode`, `anomaly_tolerances` (JSON), `processor_description_patterns` (array), `processor_follow_window_days` (int).
- Permissions: reuse the Profit & Loss report permission check for access.

11) Tests and sample data
- Provide unit tests for: FIFO consumption, serial matching, shipping allocation (value vs dimensions), payment follow-through (processor -> final account), anomaly flagging thresholds.

Appendix: When the serial-number module becomes available
- Add join to `serial_tracking` table matching `serial_id` or `stock_move.serial_no` and use exact serial matching rather than FIFO. In this case, `in_qty` and `out_qty` are 1-per-serial and trace lines become per-serial.
