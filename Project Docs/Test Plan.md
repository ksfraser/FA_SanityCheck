# Test Plan — FA Sanity Check Module

Scope: unit tests, integration tests (with FA DB), and UAT scenarios for Quarter/Year close checks.

1) Unit Tests
- Verify `ItemTrace::fifoConsume()` with varied combinations: exact matches, partial consumes, transfers between locations, serial-tracked shortcuts.
- Verify `ItemTrace::followPayment()` for direct final deposits, processor holds, settled transfers with fees, and missing transfer cases.
- Test anomaly flagging logic for thresholds (RED/YELLOW) and date anomalies.

2) Integration Tests (DB-backed)
- Setup a test FA DB schema snapshot with sample data for: purchases, GRNs, supplier invoices/payments, sales invoices/payments, bank_trans, gl_trans.
- Run end-to-end traces for selected `stock_id` values and verify returned timeline and linked document IDs.
- Run Trial Balance Diagnostics with intentionally introduced issues (unposted journal, currency mismatch) and assert diagnostics list contains expected items.

3) Reconciliation Tests
- Populate subledger sample data and GL rows; run GL↔subledger reconciliation and assert matched/unmatched counts and that suggested resolution links point to the correct records.

4) AR/AP Aging Tests
- Create invoices and partially/applied payments; verify aging buckets and unapplied-payment detection.

5) Performance & Load Tests
- Run FIFO allocation and trace generation on datasets sized to emulate production (e.g., 100k `stock_moves`) and measure response time; identify bottlenecks and add indexes as required (recommend indexes on `stock_moves(transaction_date, stock_id, location_id)` and `bank_trans(account_id, transaction_date, deposit_id)`).

6) UAT Scenarios (Quarter/Year close)
- Scenario A: Normal close — verify reconciliations green.
- Scenario B: TB out of balance due to unposted journal — system identifies the specific unposted `gl_trans`.
- Scenario C: Processor delay — payment recorded in processor account but final transfer not within window — system flags processor_hold.

7) Acceptance Criteria for Tests
- Unit tests covering core algorithms should pass (PHPUnit). Integration tests run against a controlled FA DB and validate end-to-end traces.
- Performance benchmarks meet configured thresholds (e.g., trace generation for a single item <= X seconds in UAT hardware).

8) Test Data & Fixtures
- Provide `tests/sample_data.sql` with scenarios: partial payments, 0.01 payments, unposted journals, orphaned GL lines, last-day spikes, negative cost lines.

9) Automation & CI
- Use GitHub Actions workflow (`.github/workflows/phpunit.yml`) to run unit tests and generate docs. Add integration test job (optional) that runs against a test DB container.
