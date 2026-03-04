DELIMITER $$
-- Payment following procedure for MySQL/MariaDB
-- Follows a payment from initial account to final cash accounts and extracts fees.
CREATE PROCEDURE payment_following(
  IN p_payment_id BIGINT,
  IN p_window_days INT
)
BEGIN
  -- Output temp table
  DROP TEMPORARY TABLE IF EXISTS payment_follow_result;
  CREATE TEMPORARY TABLE payment_follow_result (
    payment_id BIGINT,
    initial_account VARCHAR(64),
    initial_date DATETIME,
    initial_amount DECIMAL(18,4),
    is_processor_hold TINYINT,
    processor_account VARCHAR(64),
    final_deposit_id BIGINT,
    final_deposit_date DATETIME,
    final_deposit_amount DECIMAL(18,4),
    fee_amount DECIMAL(18,4),
    status VARCHAR(64)
  ) ENGINE=MEMORY;

  -- This implementation assumes existence of allocation/payment -> bank_trans mapping.
  -- Adjust joins to match your FA schema for payments -> bank_trans.

  DECLARE v_initial_account VARCHAR(64);
  DECLARE v_initial_date DATETIME;
  DECLARE v_initial_amount DECIMAL(18,4);
  DECLARE v_is_processor TINYINT DEFAULT 0;
  DECLARE v_processor_account VARCHAR(64);
  DECLARE v_final_id BIGINT;
  DECLARE v_final_date DATETIME;
  DECLARE v_final_amount DECIMAL(18,4);
  DECLARE v_fee_amount DECIMAL(18,4) DEFAULT 0;

  -- Find initial bank_trans record for the payment
  SELECT bt.account_id, bt.transaction_date, bt.amount
  INTO v_initial_account, v_initial_date, v_initial_amount
  FROM bank_trans bt
  JOIN payments p ON p.bank_trans_id = bt.id
  WHERE p.id = p_payment_id
  LIMIT 1;

  IF v_initial_account IS NULL THEN
    INSERT INTO payment_follow_result VALUES (p_payment_id,NULL,NULL,0,0,NULL,NULL,NULL,0,'no_initial_txn');
    SELECT * FROM payment_follow_result; LEAVE payment_follow;
  END IF;

  -- determine if account is a processor account
  IF EXISTS (SELECT 1 FROM sanity_config sc WHERE sc.config_key = 'processor_accounts' AND JSON_CONTAINS(sc.config_value, CONCAT('"',v_initial_account,'"'))) THEN
    SET v_is_processor = 1;
    SET v_processor_account = v_initial_account;
  END IF;

  IF v_is_processor = 0 THEN
    -- If initial account is final cash, mark done
    IF EXISTS (SELECT 1 FROM sanity_config sc WHERE sc.config_key = 'final_cash_accounts' AND JSON_CONTAINS(sc.config_value, CONCAT('"',v_initial_account,'"'))) THEN
      INSERT INTO payment_follow_result VALUES (p_payment_id,v_initial_account,v_initial_date,v_initial_amount,0,NULL,NULL,NULL,0,'final_direct');
      SELECT * FROM payment_follow_result; LEAVE payment_follow;
    END IF;
  END IF;

  -- If processor: search for transfers from processor -> final cash within window
  SELECT bt2.id, bt2.transaction_date, bt2.amount
  INTO v_final_id, v_final_date, v_final_amount
  FROM bank_trans bt2
  WHERE bt2.from_account = v_processor_account
    AND EXISTS (
      SELECT 1 FROM sanity_config sc2
      WHERE sc2.config_key = 'final_cash_accounts' AND JSON_CONTAINS(sc2.config_value, CONCAT('"', bt2.to_account, '"'))
    )
    AND bt2.transaction_date BETWEEN v_initial_date AND DATE_ADD(v_initial_date, INTERVAL p_window_days DAY)
  LIMIT 1;

  IF v_final_id IS NULL THEN
    INSERT INTO payment_follow_result VALUES (p_payment_id,v_initial_account,v_initial_date,v_initial_amount,1,v_processor_account,NULL,NULL,0,'processor_hold');
    SELECT * FROM payment_follow_result; LEAVE payment_follow;
  END IF;

  -- detect fee lines on the final deposit (negative amounts or fee GL lines)
  SELECT COALESCE(SUM(btline.amount * -1),0) INTO v_fee_amount
  FROM bank_trans btline
  WHERE btline.deposit_id = v_final_id AND btline.amount < 0;

  INSERT INTO payment_follow_result VALUES (p_payment_id,v_initial_account,v_initial_date,v_initial_amount,1,v_processor_account,v_final_id,v_final_date,v_final_amount,v_fee_amount,'settled');
  SELECT * FROM payment_follow_result;
END$$
DELIMITER ;
