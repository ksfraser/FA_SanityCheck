-- Sample dataset for unit testing FIFO and payment-follow logic (anonymized)
-- NOTE: IDs and minimal fields included; adjust to match your FA schema names and columns.

-- stock_master
INSERT INTO stock_master (stock_id, description) VALUES ('ITEM-001','Test Item 1');

-- locations
INSERT INTO locations (loc_code, location_name) VALUES ('LOC1','Warehouse 1');

-- inbound receipts (stock_moves) - two receipts with different costs
INSERT INTO stock_moves (id, stock_id, location_id, move_type, qty, cost, transaction_date, doc_type, doc_no)
VALUES
(1,'ITEM-001','LOC1','receipt',100,10.00,'2025-01-01','PO','PO-100'),
(2,'ITEM-001','LOC1','receipt',50,12.00,'2025-02-15','PO','PO-101');

-- outbound sale consuming FIFO (should consume from id=1 first)
INSERT INTO stock_moves (id, stock_id, location_id, move_type, qty, transaction_date, doc_type, doc_no)
VALUES
(3,'ITEM-001','LOC1','sale',120,'2025-03-01','SalesInvoice','SI-500');

-- supplier invoice and allocations (for inbound costs)
INSERT INTO supp_trans (trans_no, type, supplier_id, total, transaction_date) VALUES (200,'INVOICE',10,1600.00,'2025-01-02');

-- customer invoice and payment
INSERT INTO debtor_trans (trans_no, type, customer_id, ov_amount, transaction_date) VALUES (500,'INVOICE',20,2000.00,'2025-03-01');
INSERT INTO cust_allocations (id, trans_no, payment_id, amount) VALUES (1,500,900,1000.00);

-- bank transactions: processor deposit and final deposit with fee line
-- Processor deposit (to processor account)
INSERT INTO bank_trans (id, account_id, amount, transaction_date, description) VALUES (1000,'PROC_SQUARE',950.00,'2025-03-02','Square settlement net');
-- Transfer from processor to final cash with fee line
INSERT INTO bank_trans (id, account_id, amount, transaction_date, description, deposit_id) VALUES (1001,'BANK_MAIN',945.00,'2025-03-03','Deposit from Square',NULL);
INSERT INTO bank_trans (id, account_id, amount, transaction_date, description, deposit_id) VALUES (1002,'BANK_MAIN','-5.00','2025-03-03','Square fee',1001);

-- Example allocation showing payment went to processor first (link payment -> bank_trans may vary by FA setup)
INSERT INTO cust_allocations (id, trans_no, payment_id, amount) VALUES (2,500,1000,950.00);

-- Minimal-value payment artifact
INSERT INTO cust_allocations (id, trans_no, payment_id, amount) VALUES (3,500,1003,0.01);

-- End of sample data
