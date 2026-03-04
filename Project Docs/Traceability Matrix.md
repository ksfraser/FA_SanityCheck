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

Notes for testing: provide anonymized sample rows from `stock_moves`, `supp_trans`, `debtor_trans`, `bank_trans`, and allocation tables to validate each mapping above.
