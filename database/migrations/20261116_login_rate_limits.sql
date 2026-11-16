USE mini_network_monitor;

CREATE TABLE IF NOT EXISTS login_rate_limits (
  bucket_hash CHAR(64) NOT NULL,
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL,
  locked_until DATETIME NULL,
  PRIMARY KEY (bucket_hash),
  KEY ix_login_rate_limits_locked_until (locked_until),
  KEY ix_login_rate_limits_window_started_at (window_started_at)
) ENGINE=InnoDB;
