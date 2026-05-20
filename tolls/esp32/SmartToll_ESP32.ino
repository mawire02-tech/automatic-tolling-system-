/**
 * ============================================================
 * SMARTTOLL SYSTEM — ESP32 Firmware v2.4.0
 * ============================================================
**/
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <ESP32Servo.h>
#include <time.h>

// ---- PINS ----
#define RFID_SS_PIN    5
#define RFID_RST_PIN   4
#define TRIG_PIN       27
#define ECHO_PIN       14
#define BUZZER_PIN     12
#define LED_GREEN_PIN  25
#define LED_RED_PIN    26
#define SERVO_PIN      13

// ---- CONFIG — EDIT THESE ----
const char* WIFI_SSID     = " ";//Wi-Fi SSID of your hotspot/router
const char* WIFI_PASSWORD = " ";//Wi-Fi password of your hotspot/router
const char* SERVER_BASE   = "http://192.168.43.173/tolls/public";  // Your XAMPP server IP
const char* API_KEY       = "ak_9f4e8d2c1a6b3f7e0d5c8a9b2e4f1d3"; // From Admin > Devices
const char* DEVICE_CODE   = "BOOTH-001";

// ---- TIMING ----
const unsigned long HEARTBEAT_MS     = 3000;   // 3s heartbeat for fast command pickup
const int           HTTP_TIMEOUT     = 8000;
const int           VEHICLE_CM       = 50;    // Vehicle present threshold (cm)
const int           VEHICLE_CLEAR_CM = 80;    // Vehicle has fully cleared (cm)

// ---- SERVO ----
const int SERVO_OPEN  = 90;
const int SERVO_CLOSE = 0;

// ---- OBJECTS ----
MFRC522           rfid(RFID_SS_PIN, RFID_RST_PIN);
LiquidCrystal_I2C lcd(0x27, 16, 2);
Servo             gate;

// ---- STATE ----
bool          barrierOpen        = false;
bool          manualOverrideOpen = false;  // true = admin opened, no auto-close
bool          vehicleCleared     = true;   // true = lane is clear, safe to open again
bool          processingRFID     = false;
unsigned long lastHeartbeatAt    = 0;
unsigned long lastRFIDAt         = 0;
String        lastUID            = "";

// LCD state tracking (avoid redrawing every loop)
enum LcdState { LCD_IDLE, LCD_VEHICLE, LCD_PROCESSING, LCD_OPEN, LCD_OVERRIDE, LCD_OTHER };
LcdState currentLcdState = LCD_OTHER;

// Config pulled from server on heartbeat
float tollCar      = 35.0;
float tollMoto     = 15.0;
float tollSuv      = 50.0;
float tollTruck    = 80.0;
float tollBus      = 70.0;
bool  antiTailgate = true;

// ============================================================
void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n=== SmartToll v2.4 ===");

  pinMode(TRIG_PIN,      OUTPUT);
  pinMode(ECHO_PIN,      INPUT);
  pinMode(BUZZER_PIN,    OUTPUT);
  pinMode(LED_GREEN_PIN, OUTPUT);
  pinMode(LED_RED_PIN,   OUTPUT);

  digitalWrite(LED_RED_PIN,   HIGH);
  digitalWrite(LED_GREEN_PIN, LOW);

  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcdSet(LCD_OTHER);
  lcdPrint("SmartToll v2.4", "Starting...");

  SPI.begin();
  rfid.PCD_Init();
  delay(50);

  gate.attach(SERVO_PIN);
  closeGateNow();

  connectWiFi();
  configTime(28800, 0, "pool.ntp.org");

  lcdSet(LCD_IDLE);
  beep(100); delay(80); beep(100);
  Serial.println("Setup complete.");
}

// ============================================================
void loop() {
  unsigned long now = millis();

  // WiFi watchdog
  if (WiFi.status() != WL_CONNECTED) {
    if (currentLcdState != LCD_OTHER) {
      lcdPrint("RECONNECTING...", "Please wait     ");
      currentLcdState = LCD_OTHER;
    }
    connectWiFi();
  }

  // Heartbeat — picks up gate commands from server
  if (now - lastHeartbeatAt >= HEARTBEAT_MS) {
    sendHeartbeat();
    lastHeartbeatAt = now;
  }

  // Read ultrasonic
  float dist           = getDistance();
  bool  vehiclePresent = (dist > 0 && dist < (float)VEHICLE_CM);
  bool  laneClear      = (dist <= 0 || dist > (float)VEHICLE_CLEAR_CM);

  // ── Anti-tailgate: track when lane clears ──────────────────
  // Once a vehicle passed through, vehicleCleared goes false.
  // It only becomes true again when sensor shows lane is empty.
  if (barrierOpen && laneClear) {
    if (!vehicleCleared) {
      vehicleCleared = true;
      Serial.println("Lane cleared — safe to accept next vehicle.");
    }
  }

  // ── Auto-close logic ───────────────────────────────────────
  // Only auto-close if:
  //   1. Gate was opened by RFID toll (not manual override)
  //   2. AND the vehicle that triggered opening has cleared the lane
  //   3. OR timeout of 10s as absolute safety fallback (not 3s)
  const unsigned long ANTITAILGATE_TIMEOUT = 10000; // 10s absolute max
  if (barrierOpen && !manualOverrideOpen) {
    if (vehicleCleared) {
      // Vehicle has passed through — close the gate
      closeGateNow();
      lcdSet(LCD_IDLE);
      Serial.println("Gate closed: vehicle cleared sensor.");
    } else if ((now - lastRFIDAt) > ANTITAILGATE_TIMEOUT) {
      // Absolute safety timeout — something may have gone wrong
      closeGateNow();
      lcdSet(LCD_IDLE);
      Serial.println("Gate closed: safety timeout.");
    }
    // If vehicle still in lane (not cleared): gate stays open, anti-tailgate active
  }

  // Manual override gate stays open indefinitely — only closes via admin command
  if (barrierOpen && manualOverrideOpen) {
    if (currentLcdState != LCD_OVERRIDE) {
      lcdPrint("OVERRIDE OPEN", "Admin Control   ");
      currentLcdState = LCD_OVERRIDE;
    }
    // Still run RFID so vehicles can tap through while gate is manually open
    // (they get charged normally)
  }

  // ── LCD state machine when gate is closed ─────────────────
  if (!barrierOpen) {
    if (!vehiclePresent) {
      // Lane empty → idle
      if (currentLcdState != LCD_IDLE) {
        lcdSet(LCD_IDLE);
      }
      // No vehicle — skip RFID processing
      return;
    } else {
      // Vehicle present → prompt to scan
      if (currentLcdState != LCD_VEHICLE) {
        lcdSet(LCD_VEHICLE);
        Serial.println("Vehicle at " + String(dist, 1) + "cm — awaiting RFID.");
      }
    }
  }

  // ── RFID scan ─────────────────────────────────────────────
  if (processingRFID) return;
  if (!rfid.PICC_IsNewCardPresent()) return;
  if (!rfid.PICC_ReadCardSerial())   return;

  // Build UID (uppercase, colon-separated)
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (i > 0) uid += ":";
    if (rfid.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  Serial.println("RFID scanned: " + uid);

  // Duplicate scan guard (5s)
  if (uid == lastUID && (millis() - lastRFIDAt) < 5000) {
    Serial.println("Duplicate scan ignored.");
    return;
  }

  // ── No vehicle but RFID scanned (held card near reader) ───
  if (!vehiclePresent && !barrierOpen) {
    lcdPrint("NO VEHICLE      ", "Present Vehicle ");
    currentLcdState = LCD_OTHER;
    beepDeny();
    delay(2000);
    lcdSet(LCD_IDLE);
    return;
  }

  // ── Anti-tailgate: previous vehicle not cleared yet ───────
  if (antiTailgate && barrierOpen && !vehicleCleared) {
    lcdPrint("WAIT!           ", "Vehicle in lane ");
    currentLcdState = LCD_OTHER;
    beepDeny();
    Serial.println("Anti-tailgate: vehicle still in lane.");
    delay(1500);
    return;
  }

  // ── Process toll ──────────────────────────────────────────
  lastUID    = uid;
  lastRFIDAt = millis();
  processingRFID = true;

  lcdPrint("Processing...   ", uid.substring(0, 16));
  currentLcdState = LCD_PROCESSING;
  digitalWrite(LED_GREEN_PIN, LOW);
  digitalWrite(LED_RED_PIN,   LOW);

  processToll(uid);

  processingRFID  = false;
  currentLcdState = LCD_OTHER; // force redraw next cycle
}

// ============================================================
// LCD STATE HELPER
// ============================================================
void lcdSet(LcdState state) {
  currentLcdState = state;
  switch (state) {
    case LCD_IDLE:
      lcdPrint("SMARTTOLL READY ", "Waiting...      ");
      digitalWrite(LED_RED_PIN,   HIGH);
      digitalWrite(LED_GREEN_PIN, LOW);
      break;
    case LCD_VEHICLE:
      lcdPrint("VEHICLE DETECTED", "Scan RFID Card  ");
      digitalWrite(LED_RED_PIN,   HIGH);
      digitalWrite(LED_GREEN_PIN, LOW);
      break;
    case LCD_OPEN:
      lcdPrint("ACCESS GRANTED  ", "Have a safe trip");
      break;
    case LCD_OVERRIDE:
      lcdPrint("OVERRIDE OPEN   ", "Admin Control   ");
      break;
    default:
      break;
  }
}

// ============================================================
// TOLL PROCESSING
// ============================================================
void processToll(String uid) {
  if (WiFi.status() != WL_CONNECTED) {
    lcdPrint("OFFLINE         ", "No WiFi         ");
    beepDeny();
    delay(2000);
    lcdSet(LCD_IDLE);
    return;
  }

  HTTPClient http;
  http.begin(String(SERVER_BASE) + "/api/v1/toll/process");
  http.setTimeout(HTTP_TIMEOUT);
  http.addHeader("Content-Type",   "application/json");
  http.addHeader("X-API-Key",      API_KEY);
  http.addHeader("X-Device-Code",  DEVICE_CODE);

  StaticJsonDocument<256> req;
  req["rfid_uid"]       = uid;
  req["vehicle_type"]   = "car";
  req["direction"]      = "both";
  req["barrier_status"] = barrierOpen ? "open" : "closed";
  String body;
  serializeJson(req, body);

  int code = http.POST(body);
  Serial.printf("Toll HTTP %d\n", code);

  if (code == 200) {
    String raw = http.getString();
    StaticJsonDocument<512> res;
    if (!deserializeJson(res, raw)) {

      bool allowed = res["allow"].as<bool>();

      if (allowed) {
        float  toll    = res["toll"].as<float>();
        float  balance = res["balance"].as<float>();
        String plate   = res["plate"].as<String>();

        String l1 = "OK " + plate;
        String l2 = "BAL:PHP " + String(balance, 2);
        lcdPrint(l1.substring(0, 16), l2.substring(0, 16));
        currentLcdState = LCD_OPEN;

        digitalWrite(LED_GREEN_PIN, HIGH);
        digitalWrite(LED_RED_PIN,   LOW);
        beepAllow();

        // Mark vehicle not yet cleared — anti-tailgate starts now
        vehicleCleared = false;
        openGate();  // This is an RFID-triggered open (not manual)

        Serial.printf("GRANTED: %s PHP%.2f\n", plate.c_str(), balance);

        delay(3000);
        digitalWrite(LED_GREEN_PIN, LOW);
        digitalWrite(LED_RED_PIN,   HIGH);

      } else {
        String reason = res["reason"].as<String>();
        String line1, line2;

        if      (reason == "UNKNOWN_RFID")           { line1 = "DENIED          "; line2 = "Unknown Card    "; }
        else if (reason == "INSUFFICIENT_BALANCE")   { line1 = "LOW BALANCE     "; line2 = "Please Top Up   "; }
        else if (reason == "VEHICLE_SUSPENDED")      { line1 = "VEH SUSPENDED   "; line2 = "Contact Admin   "; }
        else if (reason == "BLACKLISTED")             { line1 = "VEHICLE BANNED  "; line2 = "See Admin       "; }
        else if (reason == "ACCOUNT_SUSPENDED")      { line1 = "ACCT SUSPENDED  "; line2 = "Contact Admin   "; }
        else                                         { line1 = "ACCESS DENIED   "; line2 = reason.substring(0, 16); }

        lcdPrint(line1, line2);
        currentLcdState = LCD_OTHER;
        digitalWrite(LED_RED_PIN,   HIGH);
        digitalWrite(LED_GREEN_PIN, LOW);
        beepDeny();

        Serial.println("DENIED: " + reason);
        delay(3000);
        lcdSet(vehiclePresent() ? LCD_VEHICLE : LCD_IDLE);
      }
    }
  } else {
    lcdPrint("SERVER ERROR    ", "Code:" + String(code) + "          ");
    currentLcdState = LCD_OTHER;
    beepError();
    delay(2000);
    lcdSet(LCD_IDLE);
  }
  http.end();
}

// Helper: re-read vehicle presence inside processToll
bool vehiclePresent() {
  float d = getDistance();
  return (d > 0 && d < (float)VEHICLE_CM);
}

// ============================================================
// HEARTBEAT
// ============================================================
void sendHeartbeat() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  http.begin(String(SERVER_BASE) + "/api/v1/device/heartbeat");
  http.setTimeout(4000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key",    API_KEY);

  StaticJsonDocument<256> req;
  req["barrier_status"] = barrierOpen ? "open" : "closed";
  req["firmware"]       = "2.4.0";
  req["ip"]             = WiFi.localIP().toString();
  String body;
  serializeJson(req, body);

  int code = http.POST(body);
  if (code == 200) {
    String raw = http.getString();
    StaticJsonDocument<2048> res;
    if (!deserializeJson(res, raw)) {

      // Apply config
      if (res.containsKey("config")) {
        JsonObject cfg = res["config"];
        if (cfg.containsKey("toll_fee_car"))         tollCar      = cfg["toll_fee_car"].as<float>();
        if (cfg.containsKey("toll_fee_motorcycle"))  tollMoto     = cfg["toll_fee_motorcycle"].as<float>();
        if (cfg.containsKey("toll_fee_suv"))         tollSuv      = cfg["toll_fee_suv"].as<float>();
        if (cfg.containsKey("toll_fee_truck"))       tollTruck    = cfg["toll_fee_truck"].as<float>();
        if (cfg.containsKey("toll_fee_bus"))         tollBus      = cfg["toll_fee_bus"].as<float>();
        if (cfg.containsKey("anti_tailgate_enabled"))antiTailgate = (cfg["anti_tailgate_enabled"].as<int>() == 1);
      }

      // Execute pending commands
      if (res.containsKey("commands") && res["commands"].is<JsonArray>()) {
        JsonArray cmds = res["commands"].as<JsonArray>();
        for (JsonObject cmd : cmds) {
          int    cmdId      = cmd["id"].as<int>();
          String command    = cmd["command"].as<String>();
          bool   needReboot = (command == "reboot");

          Serial.println("CMD: " + command + " id=" + String(cmdId));

          if (!needReboot) {
            executeGateCommand(command);
          } else {
            lcdPrint("ADMIN REBOOT    ", "Restarting...   ");
          }

          // Always ack BEFORE reboot
          ackCommand(cmdId);

          if (needReboot) {
            delay(1500);
            ESP.restart();
          }
        }
      }
    }
  }
  http.end();
}

void ackCommand(int cmdId) {
  HTTPClient http;
  http.begin(String(SERVER_BASE) + "/api/v1/device/commands");
  http.setTimeout(4000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key",    API_KEY);
  StaticJsonDocument<128> doc;
  JsonArray acks = doc.createNestedArray("acks");
  JsonObject a   = acks.createNestedObject();
  a["id"]     = cmdId;
  a["result"] = "ok";
  doc["barrier_status"] = barrierOpen ? "open" : "closed";
  String body; serializeJson(doc, body);
  http.POST(body);
  http.end();
}

// ============================================================
// GATE COMMANDS (from admin override)
// ============================================================
void executeGateCommand(String command) {
  if (command == "open_gate") {
    // Manual override: open gate, disable auto-close timer
    lcdPrint("OVERRIDE OPEN   ", "Admin Control   ");
    currentLcdState  = LCD_OVERRIDE;
    manualOverrideOpen = true;   // ← suppress auto-close
    openGateManual();
    beepAllow();
    Serial.println("Gate OPENED by admin (manual, no auto-close).");

  } else if (command == "close_gate") {
    // Manual close: re-enable normal operation
    lcdPrint("OVERRIDE CLOSE  ", "Closing Gate... ");
    currentLcdState    = LCD_OTHER;
    manualOverrideOpen = false;  // ← restore normal auto-close
    closeGateNow();
    beep(200);
    delay(300);
    lcdSet(LCD_IDLE);
    Serial.println("Gate CLOSED by admin.");

  } else if (command == "test_led_green") {
    lcdPrint("TEST MODE       ", "Green LED x3    ");
    currentLcdState = LCD_OTHER;
    for (int i = 0; i < 3; i++) {
      digitalWrite(LED_GREEN_PIN, HIGH); delay(300);
      digitalWrite(LED_GREEN_PIN, LOW);  delay(200);
    }
    digitalWrite(LED_RED_PIN, HIGH);
    lcdSet(LCD_IDLE);

  } else if (command == "test_led_red") {
    lcdPrint("TEST MODE       ", "Red LED x3      ");
    currentLcdState = LCD_OTHER;
    for (int i = 0; i < 3; i++) {
      digitalWrite(LED_RED_PIN, HIGH); delay(300);
      digitalWrite(LED_RED_PIN, LOW);  delay(200);
    }
    digitalWrite(LED_RED_PIN, HIGH);
    lcdSet(LCD_IDLE);

  } else if (command == "test_buzzer") {
    lcdPrint("TEST MODE       ", "Buzzer x3       ");
    currentLcdState = LCD_OTHER;
    for (int i = 0; i < 3; i++) { beep(200); delay(200); }
    lcdSet(LCD_IDLE);

  } else if (command == "reboot") {
    lcdPrint("ADMIN REBOOT    ", "Restarting...   ");
    // restart handled by caller after ack
  }
}

// ============================================================
// GATE HARDWARE
// ============================================================

// RFID-triggered open — auto-close is active
void openGate() {
  gate.write(SERVO_OPEN);
  barrierOpen        = true;
  manualOverrideOpen = false;  // RFID open — auto-close enabled
  vehicleCleared     = false;  // wait for vehicle to pass
  Serial.println("Gate OPEN (RFID)");
}

// Manual override open — NO auto-close
void openGateManual() {
  gate.write(SERVO_OPEN);
  barrierOpen        = true;
  manualOverrideOpen = true;   // admin open — no auto-close
  vehicleCleared     = true;   // don't block next vehicle
  Serial.println("Gate OPEN (manual override)");
}

// Close gate immediately (no delay on servo)
void closeGateNow() {
  gate.write(SERVO_CLOSE);
  barrierOpen        = false;
  manualOverrideOpen = false;
  Serial.println("Gate CLOSED");
}

// ============================================================
// HARDWARE HELPERS
// ============================================================
void beep(int ms) {
  digitalWrite(BUZZER_PIN, HIGH); delay(ms); digitalWrite(BUZZER_PIN, LOW);
}
void beepAllow() { beep(150); delay(80); beep(150); }
void beepDeny()  { beep(700); }
void beepError() { for (int i=0;i<3;i++){beep(100);delay(100);} }

void lcdPrint(String l1, String l2) {
  lcd.clear();
  lcd.setCursor(0, 0);
  while (l1.length() < 16) l1 += " ";
  lcd.print(l1.substring(0, 16));
  lcd.setCursor(0, 1);
  while (l2.length() < 16) l2 += " ";
  lcd.print(l2.substring(0, 16));
}

float getDistance() {
  digitalWrite(TRIG_PIN, LOW);  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  long dur = pulseIn(ECHO_PIN, HIGH, 25000);
  if (dur == 0) return 999.0;  // no echo = clear
  return (dur * 0.034f) / 2.0f;
}

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  Serial.print("WiFi connecting");
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries < 30) {
    delay(500); Serial.print("."); tries++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi OK: " + WiFi.localIP().toString());
    lcdPrint("WiFi Connected  ", WiFi.localIP().toString());
    beep(300);
    delay(1000);
    lcdSet(LCD_IDLE);
  } else {
    Serial.println("\nWiFi FAILED");
    lcdPrint("NO WIFI         ", "Check settings  ");
  }
}
