# SmartToll System v2.1.0
## Automated Toll Collection & Barrier Management System

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    WEB APPLICATION (PHP MVC)                │
│  ┌───────────┐  ┌──────────────┐  ┌──────────────────────┐ │
│  │   Router  │  │  Controllers │  │       Views          │ │
│  │ index.php │─▶│ Auth/Admin/  │─▶│ Layouts + Pages      │ │
│  │           │  │ User/Toll    │  │ (PHP Templates)      │ │
│  └───────────┘  └──────────────┘  └──────────────────────┘ │
│                        │                                     │
│                  ┌─────▼──────┐                             │
│                  │   Models   │                             │
│                  │  Database  │                             │
│                  └─────┬──────┘                             │
└────────────────────────┼────────────────────────────────────┘
                         │
              ┌──────────▼──────────┐
              │   MySQL Database    │
              │  toll_system DB     │
              └─────────────────────┘
                         ▲
                         │ HTTP/JSON API
                         │ X-API-Key auth
              ┌──────────┴──────────┐
              │   ESP32 DevKit v1   │
              │  ┌──────────────┐  │
              │  │ RFID MFRC522 │  │
              │  │ HC-SR04      │  │
              │  │ LCD I2C 16x2 │  │
              │  │ Servo Motor  │  │
              │  │ Buzzer + LEDs│  │
              │  └──────────────┘  │
              └─────────────────────┘
```

---

## Features

### Web Application
- **Secure Authentication**: BCrypt hashed passwords, session management, brute-force protection
- **Admin Dashboard**: Real-time charts, revenue analytics, device monitoring
- **User Portal**: Wallet, transaction history, vehicle management
- **Toll Processing API**: REST JSON API with API key authentication
- **Wallet System**: Digital toll wallet with top-up request workflow
- **Device Management**: Register ESP32 booths, monitor status, view API keys
- **Reports**: Date-filtered exports (CSV), transaction logs
- **System Logs**: Full audit trail with severity levels
- **Settings**: Live toll fee, barrier, and security configuration
- **Theme Toggle**: Dark / Light mode
- **SQL Injection Protection**: PDO prepared statements throughout
- **XSS Protection**: htmlspecialchars() on all output

### ESP32 Hardware
- Real-time RFID card reading (MFRC522)
- Vehicle detection via ultrasonic sensor
- Anti-tailgating logic
- LCD status display
- Dual LED indicators (green/red)
- Buzzer audio feedback
- Servo-controlled barrier
- Offline transaction queue (stored in NVS flash)
- Automatic sync on reconnect
- Heartbeat with server-pushed configuration
- NTP time synchronization

---

## Directory Structure

```
toll_system/
├── app/
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── AdminController.php
│   │   ├── UserController.php
│   │   └── TollController.php      ← API endpoints
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── admin.php
│   │   │   ├── user.php
│   │   │   └── auth.php
│   │   ├── admin/
│   │   │   ├── dashboard.php
│   │   │   ├── users.php
│   │   │   ├── vehicles.php
│   │   │   ├── transactions.php
│   │   │   ├── topups.php
│   │   │   ├── devices.php
│   │   │   ├── logs.php
│   │   │   └── settings.php
│   │   ├── user/
│   │   │   ├── dashboard.php
│   │   │   ├── transactions.php
│   │   │   ├── vehicles.php
│   │   │   ├── wallet.php
│   │   │   └── profile.php
│   │   └── auth/
│   │       ├── login.php
│   │       └── register.php
│   └── helpers/
│       ├── Security.php
│       ├── Validator.php
│       └── Response.php
├── config/
│   ├── app.php                     ← App bootstrap
│   ├── database.php                ← DB connection
│   └── schema.sql                  ← Full DB schema + seed data
├── public/
│   ├── index.php                   ← Front controller / Router
│   ├── css/
│   │   └── main.css
│   └── js/
│       └── app.js
├── esp32/
│   ├── SmartToll_ESP32.ino         ← Complete firmware
│   └── WIRING.md                   ← Hardware guide
└── .htaccess
```

---

## Installation

### Requirements
- PHP 8.1+
- MySQL 8.0+
- Apache 2.4+ with mod_rewrite
- ESP32 DevKit v1

### Web Server Setup

1. **Clone / copy** project to your Apache web root:
   ```bash
   cp -r toll_system/ /var/www/html/
   ```

2. **Create database** and import schema:
   ```bash
   mysql -u root -p < /var/www/html/toll_system/config/schema.sql
   ```

3. **Configure database** in `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'toll_system');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_pass');
   ```

4. **Configure app URL** in `config/app.php`:
   ```php
   define('APP_URL', 'http://your-server-ip/toll_system');
   ```

5. **Enable mod_rewrite**:
   ```bash
   a2enmod rewrite
   systemctl restart apache2
   ```

6. **Set permissions**:
   ```bash
   chmod -R 755 /var/www/html/toll_system
   chmod -R 777 /var/www/html/toll_system/public/uploads
   ```

### Default Login Credentials
| Role     | Username       | Password   |
|----------|----------------|------------|
| Admin    | admin          | password   |
| Operator | operator1      | password   |
| User     | juan_dela_cruz | password   |

> **Change all passwords immediately after first login!**

### ESP32 Firmware Setup

1. Install Arduino IDE + ESP32 board support
2. Install required libraries (see `esp32/WIRING.md`)
3. Edit WiFi and server credentials in `SmartToll_ESP32.ino`
4. Get your device API key from Admin Panel → Devices
5. Flash to ESP32
6. Wire hardware per `esp32/WIRING.md`

---

## API Reference

### POST /api/v1/toll/process
**Auth**: `X-API-Key: your_api_key`

**Request:**
```json
{
  "rfid_uid": "AA:BB:CC:DD",
  "vehicle_type": "car",
  "direction": "both",
  "offline": false
}
```

**Response (success):**
```json
{
  "allow": true,
  "message": "Access granted",
  "ref": "TXN20240101ABCD",
  "plate": "ABC-1234",
  "owner": "Juan Dela Cruz",
  "toll": 35.00,
  "balance": 465.00,
  "buzzer": "allow",
  "led": "green",
  "display": "WELCOME JUAN / BAL: PHP 465.00",
  "barrier_cmd": "open"
}
```

**Response (denied):**
```json
{
  "allow": false,
  "reason": "INSUFFICIENT_BALANCE",
  "message": "Insufficient balance",
  "buzzer": "deny",
  "led": "red",
  "display": "INSUFFICIENT BALANCE"
}
```

### POST /api/v1/device/heartbeat
Sends device status, receives updated configuration.

### POST /api/v1/sync
Batch sync offline transactions.

---

## Security Features

1. **Password Hashing**: BCrypt with cost factor 12
2. **CSRF Protection**: Per-session tokens on all forms
3. **SQL Injection**: PDO prepared statements throughout
4. **XSS Prevention**: htmlspecialchars() on all output
5. **Brute Force**: Account lockout after 5 failed attempts
6. **Session Security**: Regenerate on login, strict mode
7. **API Auth**: Per-device API keys, rate limiting
8. **Input Validation**: Server-side for all forms
9. **Security Headers**: X-Frame-Options, X-XSS-Protection

---

## License
MIT License — Free for educational and commercial use.
