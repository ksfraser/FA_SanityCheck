-- Create snapshot tables for module-level stock_moves snapshots
CREATE TABLE IF NOT EXISTS sanity_stock_moves_snapshot (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  snapshot_series VARCHAR(64) NOT NULL,
  src_id BIGINT NOT NULL,
  stock_id VARCHAR(64) NOT NULL,
  location_id VARCHAR(64) DEFAULT NULL,
  transaction_date DATETIME DEFAULT NULL,
  qty DECIMAL(18,4) DEFAULT 0,
  move_type VARCHAR(32) DEFAULT NULL,
  doc_type VARCHAR(32) DEFAULT NULL,
  doc_no VARCHAR(64) DEFAULT NULL,
  cost DECIMAL(18,4) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_snapshot_series_stock (snapshot_series, stock_id),
  INDEX ix_snapshot_series_loc (snapshot_series, location_id),
  INDEX ix_snapshot_series_date (snapshot_series, transaction_date),
  INDEX ix_snapshot_series_stock_date (snapshot_series, stock_id, transaction_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sanity_snapshot_sessions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  snapshot_series VARCHAR(64) NOT NULL,
  session_key VARCHAR(128) NOT NULL,
  user_id VARCHAR(64) DEFAULT NULL,
  criteria TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_session_series (snapshot_series),
  INDEX ix_session_key (session_key)
) ENGINE=InnoDB;
