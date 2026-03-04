DELIMITER $$
-- FIFO consumption procedure for MySQL/MariaDB
-- Note: this is a robust, readable implementation sketch. Adjust table/column names to match your FA schema.
CREATE PROCEDURE fifo_consumption(
  IN p_stock_id VARCHAR(64),
  IN p_location_id VARCHAR(32)
)
BEGIN
  -- Temporary tables for inbound batches and outbound orders
  DROP TEMPORARY TABLE IF EXISTS inbox_batches;
  CREATE TEMPORARY TABLE inbox_batches (
    batch_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    src_doc_type VARCHAR(32),
    src_doc_no VARCHAR(64),
    txn_date DATETIME,
    qty DECIMAL(18,4),
    remaining_qty DECIMAL(18,4)
  ) ENGINE=MEMORY;

  DROP TEMPORARY TABLE IF EXISTS out_orders;
  CREATE TEMPORARY TABLE out_orders (
    out_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    dst_doc_type VARCHAR(32),
    dst_doc_no VARCHAR(64),
    txn_date DATETIME,
    qty DECIMAL(18,4)
  ) ENGINE=MEMORY;

  -- Populate inbox_batches: receipts and transfer-in events
  INSERT INTO inbox_batches (src_doc_type, src_doc_no, txn_date, qty, remaining_qty)
  SELECT sm.doc_type, sm.doc_no, sm.transaction_date, sm.qty, sm.qty
  FROM stock_moves sm
  WHERE sm.stock_id = p_stock_id
    AND sm.location_id = p_location_id
    AND sm.move_type IN ('receipt','transfer_in')
  ORDER BY sm.transaction_date ASC, sm.id ASC;

  -- Populate out_orders: issues, sales, transfer_out
  INSERT INTO out_orders (dst_doc_type, dst_doc_no, txn_date, qty)
  SELECT sm.doc_type, sm.doc_no, sm.transaction_date, sm.qty
  FROM stock_moves sm
  WHERE sm.stock_id = p_stock_id
    AND sm.location_id = p_location_id
    AND sm.move_type IN ('issue','sale','transfer_out')
  ORDER BY sm.transaction_date ASC, sm.id ASC;

  -- Result table
  DROP TEMPORARY TABLE IF EXISTS fifo_result;
  CREATE TEMPORARY TABLE fifo_result (
    in_doc_type VARCHAR(32),
    in_doc_no VARCHAR(64),
    in_date DATETIME,
    in_qty DECIMAL(18,4),
    assigned_qty DECIMAL(18,4),
    out_doc_type VARCHAR(32),
    out_doc_no VARCHAR(64),
    out_date DATETIME
  ) ENGINE=MEMORY;

  -- Cursors for out_orders processing
  DECLARE done INT DEFAULT 0;
  DECLARE cur_out_id BIGINT;
  DECLARE cur_out_qty DECIMAL(18,4);
  DECLARE cur_out_doc_type VARCHAR(32);
  DECLARE cur_out_doc_no VARCHAR(64);
  DECLARE cur_out_date DATETIME;

  DECLARE cur_in_id BIGINT;
  DECLARE cur_in_remaining DECIMAL(18,4);
  DECLARE cur_in_doc_type VARCHAR(32);
  DECLARE cur_in_doc_no VARCHAR(64);
  DECLARE cur_in_date DATETIME;

  DECLARE out_cursor CURSOR FOR SELECT out_id, dst_doc_type, dst_doc_no, txn_date, qty FROM out_orders ORDER BY txn_date, out_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN out_cursor;
  out_loop: LOOP
    FETCH out_cursor INTO cur_out_id, cur_out_doc_type, cur_out_doc_no, cur_out_date, cur_out_qty;
    IF done THEN LEAVE out_loop; END IF;

    -- assign qty_to_assign from this outbound across inbox_batches
    DECLARE qty_to_assign DECIMAL(18,4);
    SET qty_to_assign = cur_out_qty;

    -- inner loop to consume inbox batches
    WHILE qty_to_assign > 0 DO
      -- find oldest inbox batch with remaining_qty > 0
      SELECT batch_id, remaining_qty, src_doc_type, src_doc_no, txn_date
      INTO cur_in_id, cur_in_remaining, cur_in_doc_type, cur_in_doc_no, cur_in_date
      FROM inbox_batches
      WHERE remaining_qty > 0
      ORDER BY txn_date ASC, batch_id ASC
      LIMIT 1;

      IF cur_in_id IS NULL THEN
        -- no more inbound batches; record leftover as unassigned (still record the out row with assigned 0)
        INSERT INTO fifo_result VALUES (NULL,NULL,NULL,0,0,cur_out_doc_type,cur_out_doc_no,cur_out_date);
        LEAVE out_loop;
      END IF;

      DECLARE assign_amount DECIMAL(18,4);
      SET assign_amount = LEAST(cur_in_remaining, qty_to_assign);

      INSERT INTO fifo_result VALUES (cur_in_doc_type, cur_in_doc_no, cur_in_date, cur_in_remaining, assign_amount, cur_out_doc_type, cur_out_doc_no, cur_out_date);

      -- update inbox remaining
      UPDATE inbox_batches SET remaining_qty = remaining_qty - assign_amount WHERE batch_id = cur_in_id;

      SET qty_to_assign = qty_to_assign - assign_amount;
    END WHILE;

  END LOOP;
  CLOSE out_cursor;

  -- Return result set to caller
  SELECT * FROM fifo_result ORDER BY out_date, out_doc_no, in_date;
END$$
DELIMITER ;
