#if __has_include("esp32_local.h")
#include "esp32_local.h"
#else
#include "esp32_local.h.example"
#endif
/*
 * EcoSense ESP32 Node
 * Reads DHT22, BMP280 (or BME280), BH1750, optional DS18B20
 * Posts JSON to api/ingest.php with X-Device-Key header
 * Sleeps ~2 seconds between readings (DeepSleep)
 * Basic OTA stub and LED status feedback
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <DHT.h>
#include <Adafruit_BMP280.h>
#include <BH1750.h>

// --- Configuration ---
const int   SLEEP_SEC     = 2;
const int   LED_PIN       = 2; // built-in LED on many ESP32 boards

// Sensors
#define DHTPIN 4
#define DHTTYPE DHT22
DHT dht(DHTPIN, DHTTYPE);

Adafruit_BMP280 bmp; // I2C: SDA/GPIO21, SCL/GPIO22 by default on many boards
BH1750 lightMeter(0x23);

void setup() {
  Serial.begin(115200);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  Wire.begin();

  // Init sensors
  bool dhtOk = (dht.begin() == true);
  bool bmpOk = bmp.begin(0x76);
  bool bhOk  = lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE);

  // Connect WiFi
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 15000) {
    delay(250);
    digitalWrite(LED_PIN, !digitalRead(LED_PIN));
  }
  digitalWrite(LED_PIN, LOW);

  if (WiFi.status() == WL_CONNECTED) {
    blink(2, 100);
    // Build payload
    float tempC = NAN, hum = NAN, pres = NAN, lux = NAN;
    if (dhtOk) { tempC = dht.readTemperature(); hum = dht.readHumidity(); }
    if (bmpOk) { pres = bmp.readPressure() / 100.0; } // hPa
    if (bhOk)  { lux  = lightMeter.readLightLevel(); }

    // Fallbacks if DHT missing
    if (isnan(tempC) && bmpOk) tempC = bmp.readTemperature();

    DynamicJsonDocument doc(512);
    if (!isnan(tempC)) doc["temperature_c"] = round(tempC * 100) / 100.0;
    if (!isnan(hum))   doc["humidity_pct"]  = round(hum * 100) / 100.0;
    if (!isnan(pres))  doc["pressure_hpa"]  = round(pres * 100) / 100.0;
    if (!isnan(lux))   doc["lux"]           = round(lux * 100) / 100.0;
    doc["device_uid"]  = DEVICE_UID;
    String body;
    serializeJson(doc, body);

    HTTPClient http;
    String url = String(API_HOST) + String(API_PATH);
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Device-Key", DEVICE_APIKEY);

    int code = http.POST(body);
    if (code > 0) {
      String resp = http.getString();
      Serial.printf("HTTP %d: %s\n", code, resp.c_str());
      if (code == 200) blink(3, 80); else blink(5, 120);
    } else {
      Serial.printf("HTTP error: %s\n", http.errorToString(code).c_str());
      blink(6, 150);
    }
    http.end();
  } else {
    Serial.println("WiFi connect failed");
    blink(10, 100);
  }

  // Deep sleep
  esp_sleep_enable_timer_wakeup((uint64_t)SLEEP_SEC * 1000000ULL);
  esp_deep_sleep_start();
}

void loop() {
  // not used in deep sleep mode
}

void blink(int times, int d) {
  for (int i=0; i<times; i++) {
    digitalWrite(LED_PIN, HIGH); delay(d);
    digitalWrite(LED_PIN, LOW);  delay(d);
  }
}

// Minimal OTA stub - extend as needed
void update_http() {
  // Placeholder: implement HTTP OTA if required
}