-- Create table used by the Sanity Check module to store configuration
CREATE TABLE IF NOT EXISTS sanity_config (
  config_key VARCHAR(128) NOT NULL PRIMARY KEY,
  config_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
