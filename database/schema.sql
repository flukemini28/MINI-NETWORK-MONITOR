CREATE DATABASE IF NOT EXISTS mini_network_monitor
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mini_network_monitor;

CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_key VARCHAR(24) NOT NULL,
  role_name VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_role_key (role_key)
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (role_key, role_name) VALUES
  ('admin', 'ผู้ดูแลระบบ'),
  ('operator', 'เจ้าหน้าที่ IT'),
  ('viewer', 'ผู้ดูข้อมูล');

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id TINYINT UNSIGNED NOT NULL,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(254) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_role_active (role_id, is_active),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS networks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  network_cidr VARCHAR(43) NOT NULL,
  gateway_ip VARCHAR(45) NULL,
  department VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_networks_name_cidr (name, network_cidr),
  KEY ix_networks_active (is_active),
  CONSTRAINT fk_networks_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  network_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  device_type ENUM('computer', 'server', 'printer', 'router', 'switch', 'other') NOT NULL DEFAULT 'other',
  department VARCHAR(120) NULL,
  location VARCHAR(180) NULL,
  current_status ENUM('online', 'offline', 'unknown') NOT NULL DEFAULT 'unknown',
  response_time_ms DECIMAL(10, 3) NULL,
  last_checked_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_devices_network_ip (network_id, ip_address),
  KEY ix_devices_status_active (current_status, is_active),
  KEY ix_devices_department (department),
  CONSTRAINT fk_devices_network FOREIGN KEY (network_id) REFERENCES networks (id),
  CONSTRAINT fk_devices_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS probe_checks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id BIGINT UNSIGNED NOT NULL,
  status ENUM('online', 'offline', 'unknown') NOT NULL,
  response_time_ms DECIMAL(10, 3) NULL,
  checked_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
  detail VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY ix_probe_checks_device_time (device_id, checked_at),
  KEY ix_probe_checks_status_time (status, checked_at),
  CONSTRAINT fk_probe_checks_device FOREIGN KEY (device_id) REFERENCES devices (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS status_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id BIGINT UNSIGNED NOT NULL,
  previous_status ENUM('online', 'offline', 'unknown') NOT NULL,
  new_status ENUM('online', 'offline', 'unknown') NOT NULL,
  occurred_at DATETIME NOT NULL,
  detail VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY ix_status_events_device_time (device_id, occurred_at),
  KEY ix_status_events_new_time (new_status, occurred_at),
  CONSTRAINT fk_status_events_device FOREIGN KEY (device_id) REFERENCES devices (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alerts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status_event_id BIGINT UNSIGNED NOT NULL,
  acknowledged_by BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alerts_status_event (status_event_id),
  KEY ix_alerts_ack_created (acknowledged_at, created_at),
  CONSTRAINT fk_alerts_status_event FOREIGN KEY (status_event_id) REFERENCES status_events (id),
  CONSTRAINT fk_alerts_acknowledged_by FOREIGN KEY (acknowledged_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_app_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS worker_heartbeats (
  worker_name VARCHAR(100) NOT NULL,
  last_seen_at DATETIME NOT NULL,
  status ENUM('running', 'error', 'stopped') NOT NULL,
  detail VARCHAR(255) NULL,
  PRIMARY KEY (worker_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  detail JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_logs_user_time (user_id, created_at),
  KEY ix_audit_logs_entity_time (entity_type, entity_id, created_at),
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
  ('monitor_interval_seconds', '60'),
  ('offline_after_failures', '2'),
  ('probe_timeout_ms', '1500');
