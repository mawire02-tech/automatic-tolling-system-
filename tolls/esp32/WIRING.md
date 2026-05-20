# SmartToll ESP32 Wiring & Setup Guide
## Hardware Connections

### MFRC522 RFID Reader (SPI)
| RFID Pin | ESP32 Pin | Notes             |
|----------|-----------|-------------------|
| SDA(SS)  | GPIO 5    | Chip Select       |
| SCK      | GPIO 18   | SPI Clock         |
| MOSI     | GPIO 23   | SPI Data Out      |
| MISO     | GPIO 19   | SPI Data In       |
| IRQ      | (NC)      | Not connected     |
| GND      | GND       |                   |
| RST      | GPIO 22   | Reset             |
| 3.3V     | 3.3V      | Power             |

### HC-SR04 Ultrasonic Sensor
| HC-SR04 | ESP32 Pin | Notes          |
|---------|-----------|----------------|
| VCC     | 5V        | Power          |
| GND     | GND       |                |
| TRIG    | GPIO 25   | Trigger pulse  |
| ECHO    | GPIO 26   | Echo input     |

> Note: HC-SR04 ECHO outputs 5V. Use voltage divider: 1kΩ + 2kΩ resistors to drop to 3.3V.

### 16x2 LCD (I2C Module)
| LCD I2C | ESP32 Pin | Notes     |
|---------|-----------|-----------|
| VCC     | 5V        | Power     |
| GND     | GND       |           |
| SDA     | GPIO 21   | I2C Data  |
| SCL     | GPIO 22   | I2C Clock |

> Default I2C address: 0x27. Run I2C scanner if LCD not found.

### Buzzer (Active)
| Buzzer | ESP32 Pin | Notes              |
|--------|-----------|--------------------|
| +      | GPIO 27   | Via 100Ω resistor  |
| -      | GND       |                    |

### LED Indicators
| LED       | ESP32 Pin | Notes               |
|-----------|-----------|---------------------|
| Green (+) | GPIO 32   | Via 220Ω resistor   |
| Red (+)   | GPIO 33   | Via 220Ω resistor   |
| Both (-)  | GND       |                     |

### Servo Motor (Barrier)
| Servo  | ESP32 Pin | Notes          |
|--------|-----------|----------------|
| Signal | GPIO 13   | PWM signal     |
| VCC    | 5V (ext)  | External 5V supply recommended |
| GND    | GND       |                |

> Use external 5V supply for servo to avoid ESP32 power issues.

---

## Required Arduino Libraries
Install via Arduino IDE Library Manager:
- `MFRC522` by GithubCommunity
- `LiquidCrystal I2C` by Frank de Brabander  
- `ESP32Servo` by Kevin Harrington
- `ArduinoJson` by Benoit Blanchon (v6.x)

---

## Schematic Overview
```
                    ┌─────────────────────────────┐
                    │         ESP32 DevKit         │
                    │                              │
    [RFID MFRC522]──┤ GPIO5(SS) GPIO18 19 23 22   │
                    │                              │
    [HC-SR04]───────┤ GPIO25(TRIG) GPIO26(ECHO)   │
                    │                              │
    [LCD I2C]───────┤ GPIO21(SDA) GPIO22(SCL)     │
                    │                              │
    [BUZZER]────────┤ GPIO27                       │
    [LED GREEN]─────┤ GPIO32                       │
    [LED RED]───────┤ GPIO33                       │
    [SERVO]─────────┤ GPIO13                       │
                    │                              │
                    │ 3.3V──┬──RFID VCC            │
                    │ 5V────┼──LCD, HC-SR04, Servo │
                    │ GND───┴──All GND             │
                    └─────────────────────────────┘
```

---

## Configuration (firmware)
Edit these constants in `SmartToll_ESP32.ino`:

```cpp
const char* WIFI_SSID     = "your_wifi_name";
const char* WIFI_PASSWORD = "your_wifi_pass";
const char* SERVER_BASE   = "http://192.168.1.100/toll_system/public";
const char* API_KEY       = "your_device_api_key";  // From admin panel
const char* DEVICE_CODE   = "BOOTH-001";
```

Get the API key from the Admin Panel → Devices → your device → "API Key" button.

---

## Operation Flow
1. ESP32 boots, connects to WiFi, syncs time via NTP
2. Ultrasonic sensor monitors for incoming vehicles
3. When vehicle detected: LCD shows "Scan RFID Card"
4. User/vehicle RFID card is scanned
5. ESP32 sends HTTP POST to `/api/v1/toll/process` with RFID UID + vehicle type
6. Server validates card, deducts toll, returns allow/deny response
7. If **allowed**: 
   - Green LED on, beep twice
   - Servo opens barrier (90°)
   - LCD shows owner name + remaining balance
   - Barrier closes after 3 seconds
8. If **denied**:
   - Red LED on, long beep
   - LCD shows denial reason
   - Barrier stays closed
9. If **offline**:
   - Transaction queued in flash memory
   - Optimistic access granted (configurable)
   - Synced automatically when WiFi reconnects

---

## Heartbeat & Config Sync
Every 30 seconds, the ESP32 sends a heartbeat to:
`POST /api/v1/device/heartbeat`

This also pulls updated settings from the server:
- Toll fees per vehicle type
- Barrier timing
- Anti-tailgating toggle

---

## Fault Tolerance
- Up to **50 offline transactions** stored in flash (NVS)
- Auto-sync when WiFi reconnects
- WiFi reconnect attempts on each loop iteration
- HTTP timeout: 8 seconds (won't hang the system)
- WDT (Watchdog) handled by ESP32's RTOS
