# EcoSense: Connect existing website to ESP8266

This is your original PHP website with an ESP8266 integration added. Original ESP32 firmware remains for reference; use `device/ecosense_esp8266.ino` for your board.

1. Back up your existing website and database. Extract this ZIP into `C:\xampp\htdocs\ecosense` (or use your actual project folder).
2. Start Apache and MySQL in XAMPP. The original website schema expects `devices` and `readings` tables in the configured database. The previous `weather_readings` table alone is **not enough**. Check phpMyAdmin first. Do NOT import `database/schema.sql` over an existing database; it contains demo data and setup statements.
3. In `config/config.php`, set `DB_NAME` to the database that contains the website's `devices` and `readings` tables. The ZIP defaults to `ecosense_db` based on your earlier setup. If those tables are missing, make a NEW empty database and import the website's `database/schema.sql` there; this schema includes demo records.
4. For an existing website database, run `database/migration_rain.sql` ONCE in phpMyAdmin to add `rain_raw`. If creating a NEW database using the modified schema, do NOT run that migration.
5. Generate a random 64-character hex key (e.g. `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`), replace the placeholder in `database/register_esp8266.sql`, and execute the SQL ONCE. Never expose the key publicly. If UID already exists, update it instead.
6. Open `device/ecosense_esp8266.ino` in Arduino IDE. Set your own Wi-Fi SSID/password, laptop IPv4 address from Windows `ipconfig`, actual website folder path, and the exact same API key. Keep NodeMCU 1.0 selected. Install DHT sensor library and Adafruit Unified Sensor.
7. Wiring with power disconnected: DHT22 + to 3V, OUT to D2, - to G; rain module VCC to 3V, GND to G, AO to A0; DO unused. Verify your NodeMCU A0 input voltage rating.
8. ESP8266 and laptop must be on a reachable local network. Allow Apache on the Windows private-network firewall. Test `http://YOUR_LAPTOP_IP/ecosense/api/latest.php` from a phone on the same Wi-Fi. Do not expose XAMPP publicly.
9. Upload firmware. Serial Monitor 115200 should show `POST HTTP status: 200` and JSON `ok:true`. Open `http://localhost/ecosense/dashboard.php`. The rain panel refreshes every 5 seconds; existing temperature/humidity dashboard updates via existing API.
10. If `HTTP 401`: wrong UID/key. If `HTTP 500`: DB or missing rain_raw column. If `HTTP -1`: wrong IP, URL, Apache/firewall or network. If HTTP 429: rate limiting. If API latest shows demo devices, do not confuse those with live NodeMCU data.

Rain sensor: value is RAW ONLY, because prior dry/wet tests did not calibrate a threshold. A raw value is NOT a confirmed rain/no-rain reading.

SECURITY: The Wi-Fi password previously shown in chat/screenshots should be changed. The API uses plain HTTP and should stay on a trusted private LAN; for remote hosting use HTTPS.
