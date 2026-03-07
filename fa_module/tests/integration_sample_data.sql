-- Sample data for reconciliation integration tests
-- NOTE: Adjust IDs and account codes to match your FA schema before running in a test DB.

-- GL: sample GL transactions
INSERT INTO gl_trans (type, type_no, account, amount, tran_date, period_no, person_type_id, person_id) VALUES
(100,1,'bank_1',100.00,'2026-01-15',1,NULL,NULL),
(100,2,'bank_1',-90.00,'2026-01-16',1,NULL,NULL),
(200,1,'ar_ledger',500.00,'2026-02-01',1,10,1);

-- bank_trans sample
INSERT INTO bank_trans (id, bank_act, transaction_date, amount) VALUES
(1,'bank_1','2026-01-15',100.00),
(2,'bank_1','2026-01-16',-90.00);

-- debtor_trans sample
INSERT INTO debtor_trans (id, debtor_no, tran_date, ov_amount, ov_freight, ov_gst) VALUES
(1,1,'2026-02-01',500.00,0,0);

-- stock_moves sample (for snapshot)
INSERT INTO stock_moves (id, stock_id, location_id, transaction_date, qty, move_type, doc_type, doc_no, unit_cost) VALUES
(1,'ITEM1','LOC1','2026-01-05',10,'receipt','GRN','GRN1',5.00),
(2,'ITEM1','LOC1','2026-02-10',-2,'sale','INV','INV1',5.00);
