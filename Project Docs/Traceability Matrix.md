# Traceability Matrix — Business Rules → Data / Implementation

This matrix maps each business requirement to the FA data elements and implementation notes so developers know exactly how to implement and test.

- BR-1: Link inventory movements to source docs
  - Data: `purch_lines.purch_no`, `grn_items.grn_no`, `supp_trans.trans_no`, `stock_moves.reference` and `stock_moves.stock_id`
  - Implementation: follow PO → GRN → Supplier Invoice chain using `stock_id` on lines. Show document IDs and links in trace.

- BR-2a: FIFO per location
  - Data: `stock_moves.location_id`, `stock_moves.transaction_date`, `stock_moves.qty`
  - Implementation: apply FIFO consumption algorithm per `location_id`. Inventory transfers are recorded as `transfer_out` + `transfer_in` and must be applied to change location batches.

- BR-3a: Shipping allocation
  - Data: `supp_trans.total_shipping`, `supp_trans.tax`, `supp_trans.line_values`, `stock_master.weight` (if present)
  - Implementation: default allocate `total_shipping` proportionally by line extended value. If `shipping_allocation_mode=use_dimensions`, allocate by volumetric weight using `stock_master` dimensions.

- BR-4: Discounts attribution
  - Data: `debtor_trans.line_discount`, `cust_allocations.payment_discount`
  - Implementation: invoice-line discounts applied to their lines. Payment discounts allocated proportionally across line items unless exactly equal to a line amount.

- BR-5: Payment tracing & processors
  - Data: `cust_allocations`, `supp_allocations`, `bank_trans.account_id`, `bank_trans.amount`, `bank_trans.description`, `gl_trans` lines
  - Implementation: match payments via allocation tables. If payment `account_id` in `processor_accounts`, search for subsequent `bank_trans` where `from_account` = processor and `to_account` in `final_cash_accounts` within `processor_follow_window_days`.

- BR-5d: Processor fees
  - Data: `bank_trans` lines on same deposit, `gl_trans` fee lines
  - Implementation: detect negative/fee lines on deposits or separate GL fee lines; attach `fee_amount` to `processor_transfers` element.

- BR-6: Anomalies
  - Data: differences between `debtor_trans.ov_amount` and SUM(`cust_allocations.amount`) ; `stock_moves` dates
  - Implementation: compute diffs; if outside `anomaly_tolerances`, flag; compute date-based anomalies (sale before receipt) and set severity.

- BR-7: Trial Balance Diagnostics
  - Data: `gl_trans`, `voided`/`posted` flags, `audit_trail` (if present), `currency_id`, `period_no`
  - Implementation: aggregate GL debits/credits per period and compare to trial balance; detect unposted journals (posted = 0), currency rounding mismatches, and unapplied allocations; provide drill-down query links to suspect `gl_trans` rows.

- BR-8: GL ↔ Subledger Reconciliation
  - Data: `bank_trans`, `debtor_trans`, `supp_trans`, `stock_moves`, `fixed_assets`, `gl_trans`
  - Implementation: for each mapped GL account run matching algorithms to subledger rows (by reference, date, amount) and produce matched/unmatched lists. Provide links and suggested actions for unmatched items.

- BR-9: AR/AP Aging & Unapplied Payments
  - Data: `debtor_trans.tran_date`, `debtor_trans.due_date`, `cust_allocations`, `supp_allocations`
  - Implementation: produce ageing buckets, detect unapplied or partially applied payments and flag invoices with allocations that don't sum to invoice totals.

- BR-10: Journal Entry Controls
  - Data: `gl_trans`, `users`, `audit_trail`
  - Implementation: highlight manual entries, large adjustments (configurable threshold), out-of-period postings, and provide user and timestamp info for review.

- BR-11: Cut-off & Period-End Checks
  - Data: `stock_moves.transaction_date`, `debtor_trans.tran_date`, `supp_trans.tran_date`, `periods` table
  - Implementation: find transactions dated outside the current reporting period, shipments with invoice dates in another period, last-day posting spikes and flag discrepancies.

- BR-12: Suspense & Clearing Accounts
  - Data: configured suspense account list, `gl_trans` balances
  - Implementation: summarize recurring balances and identify candidate clearing entries by matching references and amounts.

- BR-13: Audit Trail Completeness
  - Data: existence checks linking `gl_trans` to source documents (PO, invoice ids), `documents` storage paths
  - Implementation: report orphaned GL lines with missing source docs and provide severity and suggested remediation steps.

Notes for test coverage: expand sample datasets to include unposted journals, partial allocations, orphaned GL lines, currency differences, and last-day spikes to validate diagnostics and reconciliation routines.

Notes for testing: provide anonymized sample rows from `stock_moves`, `supp_trans`, `debtor_trans`, `bank_trans`, and allocation tables to validate each mapping above.
