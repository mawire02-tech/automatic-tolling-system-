-- ============================================================
-- SmartToll v2.4 Migration Patch
-- Run this on existing installations to apply v2.4 changes
-- ============================================================
USE toll_system1;

-- 1. Change currency symbol default to $
UPDATE system_settings SET setting_value = '$' WHERE setting_key = 'currency_symbol';

-- 2. Change payment_method ENUM to include new methods
-- MySQL allows adding new ENUM values
ALTER TABLE topup_requests
  MODIFY COLUMN payment_method ENUM('ecocash','onemoney','bank','cash','bank_transfer','gcash','maya','card') DEFAULT 'cash';

-- 3. Add pending status route for operators (users table already has pending status)
-- Ensure operator seed is set correctly
UPDATE users SET status = 'active' WHERE username = 'operator1' AND role = 'operator';

-- 4. Verify system_settings currency_symbol exists
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group, description)
VALUES ('currency_symbol', '$', 'general', 'Currency symbol (e.g. $ or ₱ or £)');

-- ── v2.4 New Features Migration ──────────────────────────────

-- System alerts table
CREATE TABLE IF NOT EXISTS system_alerts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_type  ENUM('low_balance','denied_spike','device_offline','suspicious','system','maintenance') DEFAULT 'system',
  severity    ENUM('info','warning','critical') DEFAULT 'info',
  title       VARCHAR(200) NOT NULL,
  message     TEXT,
  related_id  INT UNSIGNED DEFAULT NULL,
  is_read     TINYINT(1) DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Vehicle blacklist table
CREATE TABLE IF NOT EXISTS vehicle_blacklist (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plate_number VARCHAR(20) NOT NULL,
  rfid_uid     VARCHAR(50) DEFAULT NULL,
  reason       VARCHAR(255) NOT NULL,
  blacklisted_by INT UNSIGNED DEFAULT NULL,
  is_active    TINYINT(1) DEFAULT 1,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME DEFAULT NULL,
  notes        TEXT,
  UNIQUE KEY uq_plate (plate_number)
) ENGINE=InnoDB;

-- Device maintenance log
CREATE TABLE IF NOT EXISTS device_maintenance (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id   INT UNSIGNED NOT NULL,
  mtype       ENUM('scheduled','repair','inspection','firmware','cleaning') DEFAULT 'scheduled',
  status      ENUM('pending','in_progress','done','overdue') DEFAULT 'pending',
  title       VARCHAR(200) NOT NULL,
  notes       TEXT,
  scheduled_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  technician  VARCHAR(100) DEFAULT NULL,
  created_by  INT UNSIGNED DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert sample alerts
INSERT IGNORE INTO system_alerts (alert_type, severity, title, message, is_read) VALUES
('system','info','System started','SmartToll PRO v2.4 initialized successfully.',1),
('device_offline','warning','Booth offline check','Verify all booths are connected before peak hours.',0);

-- ── v2.5 New Feature Tables ──────────────────────────────────

CREATE TABLE IF NOT EXISTS vehicle_blacklist (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) NOT NULL,
    reason       TEXT,
    added_by     INT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plate (plate_number),
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS operator_shifts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    device_id   INT NULL,
    start_time  DATETIME NOT NULL,
    end_time    DATETIME NULL,
    notes       TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id)   REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    type       ENUM('info','warning','success','danger') DEFAULT 'info',
    subject    VARCHAR(200) NOT NULL,
    message    TEXT NOT NULL,
    is_read    TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add Anthropic API key to settings
