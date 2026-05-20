-- ============================================================
-- SMARTTOLL SYSTEM v2.2 — Full Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+ (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS toll_system1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE toll_system1;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS device_commands;
DROP TABLE IF EXISTS offline_queue;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS topup_requests;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS rfid_cards;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users
CREATE TABLE users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  UNIQUE NOT NULL,
    email          VARCHAR(100) UNIQUE NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    full_name      VARCHAR(100) NOT NULL,
    phone          VARCHAR(20)  DEFAULT NULL,
    role           ENUM('admin','operator','user') DEFAULT 'user',
    status         ENUM('active','suspended','pending') DEFAULT 'active',
    wallet_balance DECIMAL(12,2) DEFAULT 0.00,
    last_login     DATETIME DEFAULT NULL,
    login_attempts INT DEFAULT 0,
    locked_until   DATETIME DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Devices / Toll Booths
CREATE TABLE devices (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    device_code         VARCHAR(20)  UNIQUE NOT NULL,
    device_name         VARCHAR(100) NOT NULL,
    location            VARCHAR(200),
    api_key             VARCHAR(64)  UNIQUE NOT NULL,
    status              ENUM('online','offline','maintenance','fault') DEFAULT 'offline',
    ip_address          VARCHAR(45)  DEFAULT NULL,
    firmware_version    VARCHAR(20)  DEFAULT '1.0.0',
    last_heartbeat      DATETIME     DEFAULT NULL,
    total_transactions  INT          DEFAULT 0,
    total_revenue       DECIMAL(14,2) DEFAULT 0.00,
    barrier_status      ENUM('open','closed','unknown') DEFAULT 'unknown',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vehicles
CREATE TABLE vehicles (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    plate_number  VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type  ENUM('motorcycle','car','suv','truck','bus') DEFAULT 'car',
    make          VARCHAR(50)  DEFAULT NULL,
    model         VARCHAR(50)  DEFAULT NULL,
    year          YEAR         DEFAULT NULL,
    color         VARCHAR(30)  DEFAULT NULL,
    status        ENUM('active','suspended','unregistered') DEFAULT 'active',
    rfid_tag      VARCHAR(64)  UNIQUE DEFAULT NULL,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- RFID Cards
CREATE TABLE rfid_cards (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    card_uid   VARCHAR(64) UNIQUE NOT NULL,
    vehicle_id INT         DEFAULT NULL,
    user_id    INT         DEFAULT NULL,
    status     ENUM('active','inactive','blocked','unassigned') DEFAULT 'unassigned',
    issued_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used  DATETIME DEFAULT NULL,
    notes      TEXT DEFAULT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
);

-- Transactions
CREATE TABLE transactions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    transaction_ref VARCHAR(32) UNIQUE NOT NULL,
    device_id       INT NOT NULL,
    vehicle_id      INT DEFAULT NULL,
    user_id         INT DEFAULT NULL,
    rfid_uid        VARCHAR(64)  DEFAULT NULL,
    toll_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance_before  DECIMAL(12,2) DEFAULT NULL,
    balance_after   DECIMAL(12,2) DEFAULT NULL,
    vehicle_type    ENUM('motorcycle','car','suv','truck','bus') DEFAULT 'car',
    direction       ENUM('entry','exit','both') DEFAULT 'both',
    status          ENUM('success','failed','pending','denied','offline_pending') DEFAULT 'pending',
    deny_reason     VARCHAR(200) DEFAULT NULL,
    processed_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    synced_at       DATETIME DEFAULT NULL,
    is_offline      TINYINT(1) DEFAULT 0,
    FOREIGN KEY (device_id)  REFERENCES devices(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
);

-- Top-Up Requests
CREATE TABLE topup_requests (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    request_ref      VARCHAR(32) UNIQUE NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    payment_method   ENUM('ecocash','onemoney','bank','cash','bank_transfer','gcash','maya','card') DEFAULT 'cash',
    reference_number VARCHAR(100) DEFAULT NULL,
    status           ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_note       TEXT DEFAULT NULL,
    processed_by     INT DEFAULT NULL,
    requested_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at     DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- System Logs
CREATE TABLE system_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    log_type    ENUM('auth','transaction','device','admin','error','security','system') DEFAULT 'system',
    severity    ENUM('info','warning','error','critical') DEFAULT 'info',
    user_id     INT DEFAULT NULL,
    device_id   INT DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    logged_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE SET NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
);

-- System Settings
CREATE TABLE system_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_group VARCHAR(50)  DEFAULT 'general',
    description   VARCHAR(255) DEFAULT NULL,
    updated_by    INT DEFAULT NULL,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Device Command Queue (Manual Gate Override)
CREATE TABLE device_commands (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    device_id   INT NOT NULL,
    command     VARCHAR(50) NOT NULL COMMENT 'open_gate,close_gate,test_led_green,test_led_red,test_buzzer,reboot',
    params      JSON DEFAULT NULL,
    status      ENUM('pending','executed','failed','expired') DEFAULT 'pending',
    result      VARCHAR(255) DEFAULT NULL,
    issued_by   INT DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    executed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id)   ON DELETE SET NULL
);

-- Offline Transaction Buffer
CREATE TABLE offline_queue (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    device_id    INT NOT NULL,
    payload      TEXT NOT NULL,
    attempt_count INT DEFAULT 0,
    status       ENUM('pending','synced','failed') DEFAULT 'pending',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    synced_at    DATETIME DEFAULT NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id)
);

-- ============================================================
-- INDEXES for performance
-- ============================================================
CREATE INDEX idx_tx_processed   ON transactions(processed_at);
CREATE INDEX idx_tx_status      ON transactions(status);
CREATE INDEX idx_tx_user        ON transactions(user_id);
CREATE INDEX idx_tx_device      ON transactions(device_id);
CREATE INDEX idx_tx_rfid        ON transactions(rfid_uid);
CREATE INDEX idx_logs_type      ON system_logs(log_type, logged_at);
CREATE INDEX idx_cmd_device     ON device_commands(device_id, status);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Users (password for all: "password" — BCrypt hash of "password")
INSERT INTO users (username, email, password_hash, full_name, role, status, wallet_balance) VALUES
('admin',          'admin@tollsystem.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin',    'active', 0.00),
('operator1',      'operator@tollsystem.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Toll Operator',        'operator', 'active', 0.00),
('juan_dela_cruz', 'juan@example.com',        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz',       'user',     'active', 500.00),
('maria_santos',   'maria@example.com',       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Santos',         'user',     'active', 1200.00),
('pedro_reyes',    'pedro@example.com',       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pedro Reyes',          'user',     'active', 300.00);

-- Devices
INSERT INTO devices (device_code, device_name, location, api_key, status, firmware_version, barrier_status) VALUES
('BOOTH-001', 'Main Entrance Booth', 'North Gate - Main Highway', 'ak_9f4e8d2c1a6b3f7e0d5c8a9b2e4f1d3c', 'online',  '2.2.0', 'closed'),
('BOOTH-002', 'Exit Booth South',    'South Gate - Exit Ramp',    'ak_1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e', 'offline', '2.1.0', 'unknown'),
('BOOTH-003', 'Express Lane A',      'Center Lane - Express',     'ak_a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', 'online',  '2.2.0', 'closed');

-- Vehicles
INSERT INTO vehicles (user_id, plate_number, vehicle_type, make, model, year, color, rfid_tag, status) VALUES
(3, 'ABC-1234', 'car',        'Toyota', 'Vios',     2022, 'Silver', 'AA:BB:CC:DD', 'active'),
(3, 'XYZ-5678', 'motorcycle', 'Honda',  'Click',    2021, 'Red',    '11:22:33:44', 'active'),
(4, 'DEF-9012', 'suv',        'Ford',   'Explorer', 2023, 'Black',  'EE:FF:00:11', 'active'),
(5, 'GHI-3456', 'truck',      'Isuzu',  'Elf',      2020, 'White',  'AA:11:BB:22', 'active');

-- RFID Cards
INSERT INTO rfid_cards (card_uid, vehicle_id, user_id, status) VALUES
('AA:BB:CC:DD', 1, 3, 'active'),
('11:22:33:44', 2, 3, 'active'),
('EE:FF:00:11', 3, 4, 'active'),
('AA:11:BB:22', 4, 5, 'active');

-- System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_group, description) VALUES
('toll_fee_motorcycle', '15.00',          'toll_fees', 'Toll fee for motorcycles'),
('toll_fee_car',        '35.00',          'toll_fees', 'Toll fee for cars'),
('toll_fee_suv',        '50.00',          'toll_fees', 'Toll fee for SUVs/Vans'),
('toll_fee_truck',      '80.00',          'toll_fees', 'Toll fee for trucks'),
('toll_fee_bus',        '70.00',          'toll_fees', 'Toll fee for buses'),
('barrier_open_time',       '3000', 'barrier',  'Barrier open duration in ms'),
('barrier_close_delay',     '2000', 'barrier',  'Delay before closing barrier in ms'),
('anti_tailgate_enabled',   '1',    'barrier',  'Enable anti-tailgating logic'),
('low_balance_alert',       '50.00','wallet',   'Alert threshold for low wallet balance'),
('system_name',             'SmartToll System', 'general', 'System display name'),
('system_timezone',         'Asia/Manila',      'general', 'System timezone'),
('currency_symbol',         '$',                'general', 'Currency symbol (e.g. $ or Rs or £)'),
('date_format',             'Y-m-d H:i:s',      'general', 'Date/time display format'),
('theme',                   'dark',  'ui',       'Default UI theme'),
('max_login_attempts',      '5',     'security', 'Maximum login attempts before lockout'),
('lockout_duration',        '15',    'security', 'Lockout duration in minutes'),
('session_timeout',         '120',   'security', 'Session timeout in minutes'),
('api_rate_limit',          '60',    'api',      'API requests per minute per device'),
('heartbeat_interval',      '30',    'device',   'Device heartbeat interval in seconds'),
('offline_sync_enabled',    '1',     'device',   'Allow offline transaction sync');

-- Sample transactions (spread over past 7 days)
INSERT INTO transactions (transaction_ref, device_id, vehicle_id, user_id, rfid_uid, toll_amount, balance_before, balance_after, vehicle_type, status, processed_at) VALUES
('TXN20240101001', 1, 1, 3, 'AA:BB:CC:DD', 35.00, 535.00, 500.00, 'car',        'success',  NOW() - INTERVAL 6 DAY),
('TXN20240101002', 1, 3, 4, 'EE:FF:00:11', 50.00, 1250.00,1200.00,'suv',        'success',  NOW() - INTERVAL 6 DAY),
('TXN20240102001', 3, 2, 3, '11:22:33:44', 15.00, 500.00, 485.00, 'motorcycle', 'success',  NOW() - INTERVAL 5 DAY),
('TXN20240102002', 1, 4, 5, 'AA:11:BB:22', 80.00, 380.00, 300.00, 'truck',      'success',  NOW() - INTERVAL 5 DAY),
('TXN20240103001', 1, 1, 3, 'AA:BB:CC:DD', 35.00, 485.00, 450.00, 'car',        'success',  NOW() - INTERVAL 4 DAY),
('TXN20240103002', 3, 3, 4, 'EE:FF:00:11', 50.00, 1200.00,1150.00,'suv',        'success',  NOW() - INTERVAL 4 DAY),
('TXN20240103003', 1, NULL, NULL, 'XX:YY:ZZ:WW', 0.00, NULL,  NULL,'car',        'denied',   NOW() - INTERVAL 4 DAY),
('TXN20240104001', 1, 2, 3, '11:22:33:44', 15.00, 450.00, 435.00, 'motorcycle', 'success',  NOW() - INTERVAL 3 DAY),
('TXN20240104002', 3, 1, 3, 'AA:BB:CC:DD', 35.00, 435.00, 400.00, 'car',        'success',  NOW() - INTERVAL 3 DAY),
('TXN20240105001', 1, 4, 5, 'AA:11:BB:22', 80.00, 300.00, 220.00, 'truck',      'success',  NOW() - INTERVAL 2 DAY),
('TXN20240105002', 1, 3, 4, 'EE:FF:00:11', 50.00, 1150.00,1100.00,'suv',        'success',  NOW() - INTERVAL 2 DAY),
('TXN20240105003', 3, 1, 3, 'AA:BB:CC:DD', 35.00, 400.00, 365.00, 'car',        'success',  NOW() - INTERVAL 2 DAY),
('TXN20240106001', 1, 2, 3, '11:22:33:44', 15.00, 365.00, 350.00, 'motorcycle', 'success',  NOW() - INTERVAL 1 DAY),
('TXN20240106002', 3, 4, 5, 'AA:11:BB:22',  0.00, 220.00, 220.00, 'truck',      'denied',   NOW() - INTERVAL 1 DAY),
('TXN20240107001', 1, 1, 3, 'AA:BB:CC:DD', 35.00, 500.00, 465.00, 'car',        'success',  NOW()),
('TXN20240107002', 3, 3, 4, 'EE:FF:00:11', 50.00, 1100.00,1050.00,'suv',        'success',  NOW());

-- Update device stats from transactions
UPDATE devices d SET
  total_transactions = (SELECT COUNT(*) FROM transactions WHERE device_id = d.id AND status='success'),
  total_revenue      = (SELECT COALESCE(SUM(toll_amount),0) FROM transactions WHERE device_id = d.id AND status='success');

-- Sample logs
INSERT INTO system_logs (log_type, severity, user_id, device_id, action, description, ip_address) VALUES
('auth',        'info',    1,    NULL, 'LOGIN_SUCCESS', 'Admin login successful',                    '127.0.0.1'),
('transaction', 'info',    3,    1,    'TOLL_DEDUCTED', 'Toll ₱35.00 deducted for plate ABC-1234',  '127.0.0.1'),
('device',      'warning', NULL, 2,    'DEVICE_OFFLINE','Booth BOOTH-002 went offline',              '127.0.0.1'),
('security',    'warning', NULL, 1,    'UNKNOWN_RFID',  'Unknown RFID tag scanned: XX:YY:ZZ:WW',    '127.0.0.1'),
('admin',       'info',    1,    NULL, 'SETTINGS_UPDATED','System settings updated',                 '127.0.0.1');

-- Sample topup requests
INSERT INTO topup_requests (user_id, request_ref, amount, payment_method, reference_number, status, requested_at) VALUES
(3, 'TOP20240101A', 500.00, 'gcash',         'GC-001234', 'pending',  NOW() - INTERVAL 2 HOUR),
(4, 'TOP20240101B', 300.00, 'cash',          '',          'pending',  NOW() - INTERVAL 1 HOUR),
(5, 'TOP20240101C', 200.00, 'bank_transfer', 'BT-9988',   'approved', NOW() - INTERVAL 1 DAY);

-- ── SmartToll v2.5 New Feature Tables ─────────────────────────

CREATE TABLE IF NOT EXISTS vehicle_blacklist (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) NOT NULL,
    reason       TEXT,
    added_by     INT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bl_plate (plate_number),
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    type       ENUM('info','warning','success','danger') DEFAULT 'info',
    subject    VARCHAR(200) NOT NULL,
    message    TEXT NOT NULL,
    is_read    TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add Anthropic API key setting
