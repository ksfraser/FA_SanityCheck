# Functional Requirements — FA Sanity Check Module

This document lists concrete functional requirements derived from the Business Requirements and Use Cases.

FR-1: Item Trace
- FR-1.1: Provide item trace request by `stock_id` or barcode/serial.
- FR-1.2: Present chronological timeline PO→GRN→Supplier Invoice→Stock Move→Sales Invoice→Payment→Bank Deposit, with links to source documents.
- FR-1.3: Apply FIFO per `location_id` for allocation when serials not present.

FR-2: Payment Following
- FR-2.1: Identify payments posted to processor accounts and follow to final cash accounts within configurable days.
- FR-2.2: Detect and report processor fees and attach them to the trace result.

FR-3: Anomaly Detection
- FR-3.1: Flag sale-before-receipt, missing invoices, partial payments, 0.01 artifact payments, and future-dated transactions.
- FR-3.2: Severity levels (RED/YELLOW) configurable by `anomaly_tolerances`.

FR-4: Trial Balance Diagnostics
- FR-4.1: Provide a diagnostics run that enumerates causes for TB imbalance with prioritized items and drill-down links to `gl_trans` rows.
- FR-4.2: Detect unposted journals, currency mismatches, unapplied allocations, rounding drift, and out-of-period postings.

FR-5: GL ↔ Subledger Reconciliation
- FR-5.1: Reconcile GL accounts (Bank, AR, AP, Inventory, Fixed Assets) to subledger rows and list matched/unmatched items.
- FR-5.2: Support configurable account-to-subledger mappings via `reconciliation_accounts`.

FR-6: AR/AP Aging & Unapplied Payments
- FR-6.1: Produce aging buckets and flag unapplied/partially applied payments.

FR-7: Journal Controls & Cut-off
- FR-7.1: Report manual and large journal entries, out-of-period postings, and provide filters by user/date/amount.
- FR-7.2: Detect transactions dated outside period and shipment/invoice cut-off mismatches.

FR-8: Suspense & Clearing Accounts
- FR-8.1: Identify recurring balances in suspense accounts and propose candidate clearing transactions by matching references.

FR-9: Audit Pack & Export
- FR-9.1: Produce exportable audit packs (PDF/XLSX/CSV) including reconciliations, traces, and signoff metadata.

FR-10: Snapshotting & Signoff
- FR-10.1: Capture read-only snapshots of ledgers at period close and prevent retroactive edits after signoff (configurable).
- FR-10.2: Record signoff metadata (user, timestamp, comments) for audit trails.

FR-11: Admin Config & Scheduling
- FR-11.1: Admin UI to configure `final_cash_accounts`, `processor_accounts`, `reconciliation_accounts`, `anomaly_tolerances`, and `tb_diagnostics_thresholds`.
- FR-11.2: Scheduled runs for nightly diagnostics and email alerts for critical exceptions.

FR-12: Security & Access
- FR-12.1: Access control aligned with P&L report roles (use `SA_SANITY` right). Provide audit logging for access to sensitive trace outputs.

Notes: Each FR maps to one or more BRs. See Traceability Matrix for exact mappings and required data elements.
