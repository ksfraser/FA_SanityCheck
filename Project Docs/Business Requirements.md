# Business Requirements: FA Sanity Check Module

1. Business Objective
- Ensure integrity of P&L and A&L by providing end-to-end traceability for inventory movements and their monetary flows.

2. Scope
- Track inventory items from purchase through sale and final settlement into bank accounts.
- Include taxes, shipping, discounts, bank fees, and third-party processor deductions where determinable from FA documents.

3. Assumptions
- FA 2.3.X database schema is available and accessible.
- Source documents exist in FA (POs, Supplier Invoices, Payments, Sales Invoices, Customer Payments, Bank entries).
- Barcode/serial tracking fields may be present for some items; otherwise FIFO is assumed.

Additional environment notes:
- Using standard FA tables; a separate serial-number module is in design but not yet available.
- We have separate UAT and Production environments available for testing/validation.

Document linkage and authoritative fields:
- FA documents (PO, GRN/Delivery, Supplier Invoice, Supplier Payment) include `stock_id` on each relevant line in standard tables; these links will be used as the canonical path for tracing inventory items through inbound flows.
- For sales-side flows, Sales Invoice lines include `stock_id` and Customer Payment records link to invoices; use these links for outbound tracing.
- `transaction_date` is the authoritative chronological field for all sanity checks and ordering.

4. Core Business Rules
- BR-1: Every inventory movement must link to source documents where available (PO, Supplier Invoice, Goods Received, Sales Invoice).
- BR-2: If an item has an individual identifier (barcode/serial), use that ID to trace movement; otherwise use FIFO allocation.
- BR-2a: FIFO allocation must be applied per location/warehouse (FA enforces transfer before sale between locations). Different locations may have different cost batches and must be considered separately.
- BR-3: Taxes and shipping costs shown on supplier invoices are attributed to item cost and included in the item's cost basis when possible.
- BR-3a: By default, shipping costs are allocated proportionally by line value when no product weight is available. Include a configuration flag to switch allocation to use product dimensions/volumetric weight when the product dimensions module becomes available.

Document-level behavior for allocation and linking:
- When allocating taxes/shipping across invoice lines, default allocation is proportional by line value. When `shipping_allocation_mode` is set to `use_dimensions` and the product dimensions module is present, use volumetric weight-based allocation.
- Where FA documents already link `stock_id` on PO → GRN → Supplier Invoice → Supplier Payment, the module must follow those links to establish per-item flows and determine whether an item is still in inventory or was later sold.
- BR-4: Discounts applied on the sales side must be attributed to the item(s) sold and visible in the trace.
- BR-5: Payment flows must be traced from invoice to final bank account. If processed by third-parties, include processor fees and reconciled bank charges.
- BR-5a: Final bank account selection is configurable — admins will provide a list of final cash/bank accounts and a separate list of known processor accounts. The module will use these lists to detect when funds reach final cash accounts.
- BR-5b: For split payments, list each payment and its date and amount; do not attempt to prorate payments across items — show mismatches as anomalies.
- BR-5c: FA may contain minimal-value payments (e.g., 0.01) due to inability to void/zero edits; treat these as valid payments but flag them for review when they create mismatches.
- BR-5d: Processor fees frequently appear as a separate journal line in the bank deposit (example: Square net deposit + fee line). Detect fees by configured processor accounts and/or journal line descriptions and show them in the trace.

Admin configuration & detection rules:
- Admins shall select `final_cash_accounts` and `processor_accounts` from a bank-account drop-down on an Admin screen; these values drive detection of settlement endpoints and processor fee lines.
- Processor fees are expected to appear as separate bank/journal lines on funds transfers; detect fees on the transfer by matching the `processor_accounts` or by matching configured description patterns. Include detected fee lines in the trace as separate entries attributed to the processor.
- If payment is received into a non-final account (a processor account), the module must follow subsequent bank transfers to detect when/if funds reached a `final_cash_account` and report intermediate processor holds and any bank charge lines associated with the transfer.
- BR-6: Flag anomalies: missing invoices, unpaid invoices, partial payments, mismatched amounts, or payments routed to unexpected accounts.

Trace rules
- Use the FA link chain where available: PO → GRN/Delivery → Supplier Invoice → Supplier Payment (each typically contains `stock_id`) to establish inbound item movement.
- For outbound flows: link Sales Invoice → Customer Payment → Bank deposit/journal lines.
- When a payment is not deposited directly into a configured `final_cash_account`, locate the subsequent bank transfer(s) from the processor account(s) to a final cash account and include those transfers and associated bank charge lines (fees) in the item trace.
- Sort inventory consumption and allocations by `transaction_date` to implement FIFO per location/warehouse. Do not assume global FIFO across locations; account for inventory transfer transactions explicitly.

5. Non-functional Requirements
- NFR-1: Read-only analysis by default; any writes must be explicit and permissioned.
- NFR-2: Reports must render within reasonable time for typical datasets (configurable thresholds).
- NFR-3: Module compatible with PHP 7.3 and FA 2.3.X.

7. Extended Financial Controls (added)

- BR-7: Trial Balance Diagnostics — the system shall detect reasons the trial balance is out of balance (unposted/voided journals, currency mismatches, unapplied allocations, rounding drift) and provide a prioritized diagnostic list with drill-down.
- BR-8: GL ↔ Subledger Reconciliation — the system shall reconcile GL balances for Bank, AR, AP, Inventory, and Fixed Assets to their respective subledgers and provide exception reports with links to source documents.
- BR-9: AR / AP Aging & Unapplied Payments — produce aging reports, detect unapplied or partially-applied payments and flag invoices with mismatched payments.
- BR-10: Journal Entry Controls — surface manual or out-of-period journal entries, large/one-off adjustments, and users who post such entries; support filters and approval workflow recommendations.
- BR-11: Cut‑off & Period‑End Checks — detect transactions dated outside the reporting period, unusual last-day spikes, and mismatches between shipment and invoice dates.
- BR-12: Suspense & Clearing Accounts — identify recurring balances in suspense/clearing accounts and propose candidate clearing transactions.
- BR-13: Audit Trail Completeness — verify presence of source documents for GL entries (POs, invoices, delivery notes) and flag orphaned transactions.
- BR-14: Snapshot & Period Locking — allow capturing a read‑only snapshot of ledger state at period close to support audit reproducibility and prevent retro edits after signoff.

Configuration additions:
- `tb_diagnostics_thresholds`: thresholds and rules for trial balance diagnostics (e.g., rounding tolerance, currency mismatch tolerance).
- `reconciliation_accounts`: mapping of GL accounts to subledger sources for automated reconciliation routines.

Acceptance criteria (extended):
- Ability to run Trial Balance Diagnostics and receive a prioritized, actionable exception list with drill-down links.
- GL ↔ subledger reconciliation reports for Bank/AR/AP/Inventory showing matched/unmatched items and suggested resolution steps.
- Aging reports with unapplied payments flagged and drill-down to allocations.

Configuration & Administrivia
- Admins must configure:
	- `final_cash_accounts`: list of GL/bank account IDs considered final cash destinations.
	- `processor_accounts`: list of GL/bank account IDs considered third-party processors (e.g., Square, Stripe).
	- `shipping_allocation_mode`: `proportional_by_value` (default) or `use_dimensions` (switch when dimensions module available).
	- `anomaly_tolerances`: currency and percentage thresholds for flagging amount mismatches.
	- `permitted_roles`: roles allowed to access traces (default: same permissions as Profit & Loss report).

Date & Sanity Rules
- Transaction `transaction_date` is authoritative for chronological checks.
- Flag anomalies if:
	- an item is sold (Sales Invoice date) before any inbound inventory date for that item/location;
	- a return/credit predates the original sale;
	- any sale or movement is future-dated relative to transaction_date checks.

Presentation & Flagging
- Do not block operations; present anomalies visually (e.g., RED = critical mismatch, YELLOW = warning) and shaded purchase-side rows when purchase predates sale.


6. Acceptance Criteria
- Ability to request an item trace and see a chronological view of purchase → inventory → sale → payments → bank deposit.
- Evidence links (document IDs) exist for each step where data is present.
- Anomalies are listed separately with recommended follow-up actions.
