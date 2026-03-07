-- Create table used by the Sanity Check module to store configuration
CREATE TABLE IF NOT EXISTS sanity_config (
  config_key VARCHAR(128) NOT NULL PRIMARY KEY,
  config_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert idempotent defaults for the module. These use INSERT IGNORE
-- so re-running the SQL during upgrades will not overwrite existing values.
INSERT IGNORE INTO sanity_config (config_key, config_value) VALUES
  ('final_cash_accounts', '[]'),
  ('processor_accounts', '[]'),
  ('processor_follow_window_days', '30'),
  ('anomaly_tolerances', '{"red":0.05,"yellow":0.02}');
