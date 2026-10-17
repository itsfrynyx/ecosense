#if __has_include("esp32_local.h")
#include "esp32_local.h"
#else
// Single-tab Arduino IDE setup: edit these, or add esp32_local.h.
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASS     = "YOUR_WIFI_PASSWORD";
const char* API_HOST      = "http://192.168.254.110"; // e.g. http://192.168.1.100
const char* API_PATH = "/ecosenseV.2.1/api/ingest.php";
const char* DEVICE_UID    = "ECO-TH-001";
const char* DEVICE_APIKEY = "YOUR_DEVICE_API_KEY"; // match your device
#endif
#include <WiFi.h>
#include <HTTPClient.h>

#include <DHT.h>

#define DHTPIN 4
#define DHTTYPE DHT22
#define RAINPIN 27
// Most comparator modules pull DO LOW when wet. Change to HIGH if reversed.
const int RAIN_WET_LEVEL = LOW;
DHT dht(DHTPIN, DHTTYPE);

// Laptop IPv4 from ipconfig, NOT ESP8266 IP or localhost.
// Change folder name if your project is installed under another URL path.
const String nodeApiUrl = String(API_HOST) + API_PATH;
unsigned long lastSend = 0;

void setup() {
  Serial.begin(115200);
  dht.begin();
  delay(2000); // Allow DHT22 to settle after power-up.
  pinMode(RAINPIN, INPUT);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.println("EcoSense ESP32 started");
  Serial.print("Ingest URL: ");
  Serial.println(nodeApiUrl);
  WiFi.setAutoReconnect(true);
}

void loop() {
  if (millis() - lastSend < 5000 && lastSend != 0) { delay(10); return; }
  lastSend = millis();
  float temp = dht.readTemperature();
  float hum = dht.readHumidity();
  bool rainDetected = digitalRead(RAINPIN) == RAIN_WET_LEVEL;
  Serial.printf("Temp: %.2f Humidity: %.2f Rain: %s\n", temp, hum, rainDetected ? "Rain detected" : "No rain detected");
  if (isnan(temp) || isnan(hum)) {
    Serial.println("DHT22 read error: sending unavailable fields as null; rain data continues");
  }
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi reconnecting...");
    WiFi.reconnect();
    return;
  }
  WiFiClient client;
  HTTPClient http;
  http.setTimeout(5000);
  if (!http.begin(client, nodeApiUrl)) { Serial.println("HTTP begin failed"); return; }
  Serial.print("Board IP: ");
  Serial.println(WiFi.localIP());
  Serial.print("Gateway: ");
  Serial.println(WiFi.gatewayIP());
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-Key", DEVICE_APIKEY);
  String payload = String("{\"device_uid\":\"") + DEVICE_UID +
      "\",\"temperature_c\":" + (isnan(temp) ? String("null") : String(temp, 2)) +
      ",\"humidity_pct\":" + (isnan(hum) ? String("null") : String(hum, 2)) +
      ",\"rain_detected\":" + String(rainDetected ? 1 : 0) +
      ",\"signal_dbm\":" + String(WiFi.RSSI()) + "}";
  Serial.print("Payload: ");
  Serial.println(payload);
  int code = http.POST(payload);
  Serial.printf("POST HTTP status: %d\n", code);
  if (code > 0) Serial.println(http.getString());
  else {
    Serial.print("Connection error: ");
    Serial.println(HTTPClient::errorToString(code));
    Serial.print("Check Apache, laptop firewall and URL: ");
    Serial.println(nodeApiUrl);
  }
  http.end();
}
