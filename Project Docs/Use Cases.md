# Use Cases: FA Sanity Check Module

Actors:
- Accountant
- Warehouse Clerk
- Purchasing Clerk
- Sales Clerk
- Bank Reconciliation Clerk
- System (FA Sanity Check module)

Primary Use Cases

1) Track Inventory Movement
- Goal: For any inventory item show full lifecycle from purchase to final bank deposit.
- Trigger: User requests trace for an item code or barcode.
- Main flow:
  - System locates purchase transactions (PO, Supplier Invoice, Supplier Payment) and purchase metadata (taxes, shipping).
  - System locates sales transactions (Sales Invoice, Customer Payment) if sold.
  - System follows payment flows to final bank account including third-party processors and bank charges.
  - System presents timeline and links to source documents.
- Acceptance criteria:
  - Every movement includes document links and dates.
  - Fees, taxes and shipping are attributed to the item where determinable.

Behavioral specifics (per your environment):
- Use FIFO per location/warehouse. Do not assume global FIFO across locations; account for required inventory transfers between locations before sale.
- Shipping costs allocation: default to proportional-by-line-value when product weight is unavailable; provide config to use product dimensions/volumetric weight when available.
- Transaction `transaction_date` is authoritative for chronological checks; flag sales before inventory, returns before sales, or future-dated sales.
- For split payments: list all payments and dates; do not prorate payments across items. Highlight invoices/items where payment totals do not align exactly.
- Handle minimal-value payments (0.01) as real payments but highlight as possible artifacts of FA's inability to zero-out edits.
- Discounts: invoice-level discounts apply to the specific invoice line(s) they are on; payment discounts are allocated proportionally across line items unless the discount exactly matches a single item amount (treat as that item being free).
- Processor fees: detect separate fee journal lines on bank deposits (e.g., Square) and show as fee entries in the trace.
- Color/shade anomalies (RED/YELLOW) rather than blocking operations. Access controlled same as Profit & Loss report.

Trace and document linking specifics:
- Admin config: Admins will pick `final_cash_accounts` and `processor_accounts` from a bank-account drop-down on an Admin screen; these drive how payments and processors are identified.
- Canonical link chain: where FA shows linked records, follow PO → GRN/Delivery → Supplier Invoice → Supplier Payment. Each of those lines typically contains `stock_id`; use that `stock_id` to track the item into inventory and to the sale if sold.
- Sorting & FIFO: sort inbound receipts and outbound issues by `transaction_date` and apply FIFO per `location_id`/warehouse. If an item is moved between locations, follow explicit inventory transfer transactions before applying FIFO for the destination.
- Payment settlement flow: when a Customer Payment is recorded into a processor account, locate subsequent bank transfer(s) from that processor account to a `final_cash_account` and include the transfer and any bank charge lines (fees) in the trace. If no transfer is found, flag as unresolved.
- Minimal edits: be tolerant of 0.01 payments and list them; flag if they cause mismatches with invoice amounts.



2) Trace Purchase-to-Payment
- Goal: Verify purchase was paid and funds reached final bank account.
- Main flow: match Supplier Invoice → Supplier Payment → Bank deposits/charges.
- Acceptance criteria: missing or partial payments are flagged.

3) Trace Sale-to-Settlement
- Goal: Verify customer payment and deposit path (bank, CC processor) and any fees.
- Main flow: match Sales Invoice → Customer Payment → Bank deposit; include processor fees and chargebacks.

4) Barcode vs FIFO Tracking
- If barcode or serial exists: track by unique ID.
- Otherwise: apply FIFO allocation for inventory consumption.

Notes: If the serial-number module becomes available, prefer serial-level tracing; otherwise rely on FIFO per location.

5) Discounts & Attributions
- Attribute customer discounts to the specific line/items and link to the sale record.

6) Reporting & Color-coding
- Shade purchase-side rows when purchase predates sale.
- Provide filters for unresolved, partial, or multi-account flows.

Additional reporting expectations:
- Export traces to CSV and PDF for audit purposes; include raw journal lines and links to FA document IDs.
- Allow admin configuration of the `final_cash_accounts` and `processor_accounts` used to identify settlement endpoints.


Alternate flows and exceptions
- Partial payments, refund, chargebacks, internal transfers, stock adjustments, and missing documents are handled and flagged with specific reasons.
