-- FIFO consumption stored-procedure skeleton (pseudo-SQL)
-- Purpose: assign outbound quantities to inbound receipt batches per location (authoritative transaction_date)
-- NOTE: Use a procedural DB language (PL/pgsql, T-SQL) or implement in app layer for large volumes.

-- Parameters:
-- IN p_stock_id VARCHAR
-- IN p_location_id INT
-- OUT result_table temporary table with columns: inbound_doc_type, inbound_doc_no, inbound_date, inbound_qty, consumed_qty, remaining_qty, outbound_doc_type, outbound_doc_no, outbound_date

-- Pseudocode (PL-like):
-- 1) Create temp table inbox_batches ordered by transaction_date ASC
-- 2) Create temp table out_orders ordered by transaction_date ASC
-- 3) Loop each outbound order O:
--    qty_to_assign := O.qty
--    while qty_to_assign > 0:
--      pick oldest inbox batch B with remaining_qty > 0
--      assign_amount := min(B.remaining_qty, qty_to_assign)
--      insert into result_table (B.doc_type, B.doc_no, B.date, B.qty, assign_amount, B.remaining_qty - assign_amount, O.doc_type, O.doc_no, O.date)
--      update B.remaining_qty -= assign_amount
--      qty_to_assign -= assign_amount
-- 4) Return result_table

-- Example: create temp inbox batches
-- SELECT id AS batch_id, transaction_date, qty, qty AS remaining_qty, doc_type, doc_no
-- INTO TEMP TABLE inbox_batches
-- FROM stock_moves
-- WHERE stock_id = p_stock_id AND location_id = p_location_id AND move_type = 'receipt'
-- ORDER BY transaction_date ASC, id ASC;

-- Example: create temp out orders
-- SELECT id AS out_id, transaction_date, qty, doc_type, doc_no
-- INTO TEMP TABLE out_orders
-- FROM stock_moves
-- WHERE stock_id = p_stock_id AND location_id = p_location_id AND move_type IN ('issue','sale')
-- ORDER BY transaction_date ASC, id ASC;

-- Implementation note: keep stable ordering and use row-level locking if running concurrently.

-- Return schema suggestion for app consumption:
-- (in_doc_type, in_doc_no, in_date, in_qty, assigned_qty, out_doc_type, out_doc_no, out_date)

-- End of FIFO skeleton
