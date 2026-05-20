# SmartToll System v2.2 — XAMPP Setup Guide

## Quick Start (5 steps)

### 1. Copy project to XAMPP
```
Copy the `toll_system` folder to:
  Windows: C:\xampp\htdocs\toll_system\
  macOS:   /Applications/XAMPP/htdocs/toll_system/
  Linux:   /opt/lampp/htdocs/toll_system/
```

### 2. Enable mod_rewrite in XAMPP
- Open `C:\xampp\apache\conf\httpd.conf`
- Ensure this line is NOT commented out:
  ```
  LoadModule rewrite_module modules/mod_rewrite.so
  ```
- Find the `<Directory "C:/xampp/htdocs">` section and set:
  ```
  AllowOverride All
  ```
- Restart Apache in XAMPP Control Panel

### 3. Import Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create database named `toll_system` (utf8mb4)
3. Click **Import** → choose `config/schema.sql` → click **Go**

### 4. Configure Database (if needed)
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'toll_system');
define('DB_USER', 'root');
define('DB_PASS', '');       // blank for default XAMPP
```

### 5. Access the System
Open browser: `http://localhost/tolls/public/`

---

## Login Credentials
| Role     | Username       | Password   |
|----------|----------------|------------|
| Admin    | admin          | password   |
| Operator | operator1      | password   |
| User     | juan_dela_cruz | password   |
| User     | maria_santos   | password   |

> ⚠️ Change all passwords after first login!

---

## URL Structure
The system auto-detects the subfolder. If your folder name is different:
```
http://localhost/my_toll/   → works automatically
http://localhost/toll_system/ → works automatically
```

---

## Troubleshooting

**"404 Not Found" on all pages**
→ mod_rewrite is not enabled. See Step 2.

**"Database connection failed"**
→ Check `config/database.php` DB_USER and DB_PASS.

**Blank white page**
→ Enable PHP error display: in `config/app.php` set `ini_set('display_errors', 1);`

**"Access denied" on login**
→ Re-import `config/schema.sql` to reset the database.

**ESP32 cannot connect**
→ Set `SERVER_BASE` in the .ino file to `http://YOUR_PC_IP/toll_system/public`
→ Both ESP32 and PC must be on the same WiFi network.

---

## ESP32 Configuration
In `esp32/SmartToll_ESP32.ino`:
```cpp
const char* WIFI_SSID     = "Your_WiFi_Name";
const char* WIFI_PASSWORD = "Your_WiFi_Pass";
const char* SERVER_BASE   = "http://192.168.1.xxx/toll_system/public";
const char* API_KEY       = "ak_9f4e8d2c1a6b3f7e0d5c8a9b2e4f1d3c"; // BOOTH-001
```
Get API keys from: Admin Panel → Devices → [your device] → "API Key"
