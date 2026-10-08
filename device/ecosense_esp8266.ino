#if __has_include("esp8266_local.h")
#include "esp8266_local.h"
#else
#include "esp8266_local.h.example"
#endif
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>

#if __has_include("DHT.h")
#include <DHT.h>
#else
#define DHT22 22

class DHT {
public:
  DHT(uint8_t pin, uint8_t type) : _pin(pin), _type(type) {}

  void begin() {
    pinMode(_pin, INPUT_PULLUP);
  }

  float readTemperature(bool S = false) {
    float temp = NAN;
    float humidity = NAN;
    if (readSensor(temp, humidity)) {
      return temp;
    }
    return NAN;
  }

  float readHumidity() {
    float temp = NAN;
    float humidity = NAN;
    if (readSensor(temp, humidity)) {
      return humidity;
    }
    return NAN;
  }

private:
  uint8_t _pin;
  uint8_t _type;
  uint32_t _lastRead = 0;
  bool _hasRead = false;
  bool _readOk = false;
  float _temperature = NAN;
  float _humidity = NAN;

  bool readSensor(float &temperature, float &humidity) {
    // Temperature and humidity must share one DHT22 transaction.
    if (!_hasRead || millis() - _lastRead >= 2000) {
      _hasRead = true;
      _lastRead = millis();
      _temperature = NAN;
      _humidity = NAN;
      _readOk = readSensorRaw(_temperature, _humidity);
    }
    temperature = _temperature;
    humidity = _humidity;
    return _readOk;
  }

  bool readSensorRaw(float &temperature, float &humidity) {
    uint8_t data[5] = {0, 0, 0, 0, 0};

    pinMode(_pin, OUTPUT);
    digitalWrite(_pin, LOW);
    delay(20);
    digitalWrite(_pin, HIGH);
    delayMicroseconds(40);
    pinMode(_pin, INPUT_PULLUP);

    uint32_t start = micros();
    while (digitalRead(_pin) == HIGH && (micros() - start) < 100) {
      delayMicroseconds(1);
    }
    start = micros();
    while (digitalRead(_pin) == LOW && (micros() - start) < 100) {
      delayMicroseconds(1);
    }
    start = micros();
    while (digitalRead(_pin) == HIGH && (micros() - start) < 100) {
      delayMicroseconds(1);
    }

    for (uint8_t i = 0; i < 40; ++i) {
      start = micros();
      while (digitalRead(_pin) == LOW && (micros() - start) < 100) {
        delayMicroseconds(1);
      }

      uint32_t lowTime = micros() - start;
      start = micros();
      while (digitalRead(_pin) == HIGH && (micros() - start) < 100) {
        delayMicroseconds(1);
      }

      uint32_t highTime = micros() - start;
      if (lowTime >= 100 || highTime >= 100) return false;
      data[i / 8] <<= 1;
      if (highTime > lowTime) {
        data[i / 8] |= 1;
      }
    }

    uint8_t checksum = data[0] + data[1] + data[2] + data[3];
    if (checksum != data[4]) {
      return false;
    }

    humidity = ((uint16_t)data[0] << 8 | data[1]) / 10.0f;
    uint16_t rawTemp = ((uint16_t)(data[2] & 0x7f) << 8) | data[3];
    temperature = rawTemp / 10.0f;
    if (data[2] & 0x80) temperature = -temperature;
    if (humidity > 100 || temperature < -40 || temperature > 80) return false;
    return true;
  }
};
#endif

#define DHTPIN D2
#define DHTTYPE DHT22
#define RAINPIN D5
// Most comparator modules pull DO LOW when wet. Change to HIGH if reversed.
const int RAIN_WET_LEVEL = LOW;
DHT dht(DHTPIN, DHTTYPE);

// Laptop IPv4 from ipconfig, NOT ESP8266 IP or localhost.
// Change folder name if your project is installed under another URL path.
unsigned long lastSend = 0;

void setup() {
  Serial.begin(115200);
  dht.begin();
  delay(2000); // Allow DHT22 to settle after power-up.
  pinMode(RAINPIN, INPUT);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.println("EcoSense ESP8266 started");
  Serial.print("Ingest URL: ");
  Serial.println(API_URL);
  WiFi.setAutoReconnect(true);
}

void loop() {
  if (millis() - lastSend < 5000 && lastSend != 0) return;
  lastSend = millis();
  float temp = dht.readTemperature();
  float hum = dht.readHumidity();
  bool rainDetected = digitalRead(RAINPIN) == RAIN_WET_LEVEL;
  Serial.printf("Temp: %.2f Humidity: %.2f Rain: %s\n", temp, hum, rainDetected ? "Wet" : "Dry");
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
  if (!http.begin(client, API_URL)) { Serial.println("HTTP begin failed"); return; }
  Serial.print("Board IP: ");
  Serial.println(WiFi.localIP());
  Serial.print("Gateway: ");
  Serial.println(WiFi.gatewayIP());
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-Key", DEVICE_API_KEY);
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
    Serial.println(API_URL);
  }
  http.end();
}
