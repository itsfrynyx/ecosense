# EcoSense: DHT22 + digital rain sensor

Use ecosense_esp8266.ino for ESP8266 NodeMCU. Use ecosense_node.esp32.ino only for ESP32. Create separate Arduino sketch folders and copy only the chosen .ino into each folder, otherwise Arduino compiles both setup()/loop() functions.

| Connection | ESP8266 NodeMCU | ESP32 alternative |
| --- | --- | --- |
| DHT22 DATA / OUT | D2 (GPIO4) | GPIO4 |
| Rain module DO | D5 (GPIO14) | GPIO27 |
| Sensor VCC | 3.3V | 3.3V |
| Sensor GND | GND | GND |
| Rain AO | Leave disconnected | Leave disconnected |

Disconnect power before wiring. Use a 4.7–10 kΩ pull-up between DHT22 DATA and 3.3V if your module does not include one. GPIO signals must stay at 3.3V.

## Arduino IDE

1. Install board support for your board. ESP8266 uses ESP8266WiFi / ESP8266HTTPClient; ESP32 uses WiFi / HTTPClient.
2. Install Adafruit DHT sensor library and its Adafruit Unified Sensor dependency. The ESP8266 sketch also contains a fallback DHT22 reader.
3. Paste the chosen complete .ino into a new sketch.
4. Edit the placeholder settings at the top of the sketch. Alternatively, add a tab named esp8266_local.h (or esp32_local.h for ESP32) and copy the matching .h.example into it; this header overrides the inline settings.
5. Set Wi-Fi credentials, laptop LAN IPv4, API URL/path, device UID and the matching API key from Devices. The URL is http://LAPTOP_IP/ecosenseV.2.1/api/ingest.php. Do not use localhost on the board.
6. Select the matching board and port, then upload. Open Serial Monitor at 115200 baud.

## Server

Start Apache and MySQL in XAMPP. For a new database only, import database/schema.sql. For existing databases, run:

C:\xamppni\php\php.exe database/setup_rain.php

The migration adds missing rain columns, registers ECO-ESP8266-01 if absent, disables unsupported legacy rules, and adds a wet-plate rule when no rain rule exists. It preserves existing readings and credentials. Get the registered device key from Devices.

Both sketches send temperature_c, humidity_pct, rain_detected and signal_dbm every five seconds. Heat index is calculated by the server from valid DHT22 readings. A failed DHT22 read becomes null; rain reporting continues.

Rain: 0 = dry, 1 = wet, null = unavailable. DO is assumed LOW when wet. Adjust the module potentiometer; change RAIN_WET_LEVEL to HIGH only if your module behaves in reverse. This detects plate wetness, not rainfall volume or intensity; the plate may stay wet after rain stops.

## Verify

For Wi-Fi troubleshooting, reset the board with Serial Monitor open at 115200 baud. The sketch prints the settings source (inline or esp8266_local.h), configured SSID, visible 2.4 GHz networks, association events and DHCP/IP status. It allows 45 seconds before manually retrying instead of disconnecting every five seconds.

- Configured SSID not seen: check the exact network name, signal, hidden-network settings and the router's 2.4 GHz radio.
- Disconnect reason 202 or security handshake timeout: check the password and router security mode; these messages alone do not prove the password is wrong.
- Router associated but waiting for IP / DHCP timeout: check the router's DHCP server and address pool.
- Board IP printed: Wi-Fi has connected; proceed to HTTP/API checks.

Check dry plate → Dry / No rain detected, wet plate → Wet / Rain detected. Verify temperature/humidity, selected-node rain status, history, CSV export and a rain alert rule (rain_detected >= 1). Disconnect DHT22 and confirm rain continues while DHT values become unavailable. Disconnect Wi-Fi and confirm the node becomes offline after the configured window.
