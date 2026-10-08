# EcoSense

**An IoT-based system for real-time weather detection and temperature monitoring.**

ESP32 sensor nodes → Wi-Fi → PHP/MySQL ingest API → live dashboard, running on XAMPP.
This folder is the project root: `index.php` is the public landing page, everything under
`dashboard.php` and the rest of the app requires a login.

---

## Requirements

| Component | Version used |
| --- | --- |
| PHP | 8.2 (8.0+ works) with `pdo_mysql` enabled |
| MySQL / MariaDB | MariaDB 10.4 or MySQL 8 |
| Web server | Apache with `mod_rewrite` (XAMPP default) |
| Browser | Any current Chrome / Edge / Firefox / Safari |

No Composer, no npm, no build step, no CDN at runtime (Google Fonts is optional and falls back
gracefully offline).

---

## Setup on XAMPP

1. **Copy the project** into your XAMPP web root — this folder is already at
   `C:\xampp\htdocs\ecosense an iot base system for Real time weather detection and temperature monitoring`.
2. **Start Apache and MySQL** from the XAMPP control panel.
3. **Import the schema.** Open <http://localhost/phpmyadmin>, choose the **Import** tab and select
   `database/schema.sql`. It creates the `ecosense` database, all tables and 48 hours of demo
   readings — no manual database creation needed.
   From a terminal the equivalent is:
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root < "database\schema.sql"
   ```
4. **Open the landing page.** The folder name contains spaces, so the URL is percent-encoded:
   ```
   http://localhost/ecosense%20an%20iot%20base%20system%20for%20Real%20time%20weather%20detection%20and%20temperature%20monitoring/
   ```
5. **Sign in.**

   | Role | Email | Password |
   | --- | --- | --- |
   | admin | `demo@ecosense.local` | `Demo@12345` |
   | operator | `operator@ecosense.local` | `Oper@12345` |
   | viewer | `viewer@ecosense.local` | `View@12345` |

> **No hardware yet?** Sign in and press **Simulate** on the dashboard (or
> `POST api/simulate.php`). Each simulated sample follows a sine model with per-node offsets and
> noise, so the charts behave exactly like live hardware.
>
> **Want the demo data to look recent again later?** Re-import `database/schema.sql`. Readings are
> generated relative to `NOW()`, so every import gives you a fresh 48-hour window.

---

## Project layout

```
index.php              public landing page (12 sections, animated)
dashboard.php          live KPI tiles, charts, device status, alerts
devices.php            fleet management + ingest URL / curl snippet
history.php            time-series table, min/avg/max, CSV export
alerts.php             alert rule CRUD + alert log with acknowledge
settings.php           profile, °C/°F, global thresholds, retention
login.php register.php logout.php

config/config.php      every setting, guarded with defined()
includes/              db.php (lazy PDO), functions.php, icons.php, head.php, flash.php
api/                   ingest.php (device POST), latest.php, history.php, devices.php,
                       ack_alert.php, export.php, simulate.php
assets/css/            landing.css (this folder), app.css
assets/js/             landing.js, chart.js, dashboard.js
database/schema.sql    tables + seed data
device/                ESP32 Arduino sketch and wiring notes
```

---

## Configuration

Everything lives in `config/config.php` and is safe to edit — each constant is wrapped in a
`defined()` check:

| Constant | Default | Meaning |
| --- | --- | --- |
| `DB_HOST` / `DB_PORT` / `DB_NAME` | `127.0.0.1` / `3306` / `ecosense` | database location |
| `DB_USER` / `DB_PASS` | `root` / *(empty)* | XAMPP defaults |
| `OFFLINE_MODE` | `false` | set `true` to never touch the database |
| `APP_TIMEZONE` | `Asia/Manila` | must match `SET time_zone` in `schema.sql` |
| `RETENTION_DAYS` | `30` | history window used by the prune job |
| `DEVICE_ONLINE_WINDOW` | `60` | seconds without a packet before a node is Offline |
| `TEMP_WARN_HIGH` / `TEMP_CRIT_HIGH` / `TEMP_WARN_LOW` | `32` / `38` / `12` | default thresholds |
| `HUMIDITY_WARN` / `PRESSURE_LOW` | `80` / `990` | default thresholds |
| `APP_DEBUG` | `false` | set `true` to display PHP errors while developing |

Sensor values arriving at the API are range-checked server-side: temperature −60…85 °C,
humidity 0…100 %, pressure 300…1200 hPa, battery 0…100 %, RSSI −120…0 dBm. Anything outside
returns `422` and writes nothing.

---

## Device → API contract

`POST api/ingest.php` with a JSON (or form-encoded) body:

```http
POST /api/ingest.php
X-Device-Key: <64-char key from the Devices page>
Content-Type: application/json

{"device_uid":"ECO-TH-001","temperature_c":28.4,"humidity_pct":63.2,
 "pressure_hpa":1008.4,"lux":214.5,"battery_pct":92,"signal_dbm":-54}
```

Responses: `201 {"ok":true,"reading_id":1932,"alerts_triggered":0}` · `401` bad UID/key ·
`422` out of range · `429` rate limited.

The ESP32 sketch, wiring table and required libraries are in `device/`.

---

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Landing page says "database not connected" | Start MySQL in XAMPP and import `database/schema.sql` |
| `SQLSTATE[HY000] [1049] Unknown database 'ecosense'` | The schema has not been imported yet |
| `Access denied for user 'root'` | Set `DB_USER` / `DB_PASS` in `config/config.php` |
| Page loads but charts stay empty | Run **Simulate** once, or POST a reading to `api/ingest.php` |
| Charts appear shifted in time | Make `APP_TIMEZONE` and `SET time_zone` in `schema.sql` match |
| 404 on `dashboard.php` | Sign in at `login.php` first; protected pages redirect |
| Fonts look different | Expected — the landing page uses a real fallback stack when Google Fonts is unreachable |

---

## Security notes

Per-device 64-character API keys compared server-side, prepared statements only (emulated prepares
disabled), bcrypt password hashes, role checks on every mutating action, CSRF tokens on every POST,
rate limiting on login and ingest, and dynamic output escaped through `e()`. `.htaccess` blocks
direct web access to `config/` and `database/`.

Built with **ESP32 · PHP 8.2 · MySQL** — vanilla HTML, CSS custom properties and ES2020. No jQuery,
no charting library, no image services.

## API reference

### GET api/latest.php
Returns latest readings for all devices (or filtered with ?device_uid=X).
Contract matches the specified JSON with series of up to 60 points.

### GET api/history.php
Query params: device_uid, range (1h|6h|24h|7d). Returns labels and series with stats.

### POST api/ingest.php
No human session required. Accepts JSON or form-encoded. Auth via X-Device-Key header or api_key field. Validates ranges, rate-limits, inserts reading, evaluates alert rules. Returns {"ok":true,"reading_id":...,"alerts_triggered":...,"server_time":"..."}.

### GET api/devices.php
Session-authenticated. Lists fleet with latest reading and 24h min/max/avg.

### POST api/ack_alert.php
Session + CSRF. Body {"alert_id":...} to acknowledge an alert.

### GET api/export.php
Session-authenticated. Streams text/csv with header: timestamp,device_uid,temperature_c,humidity_pct,pressure_hpa,heat_index_c,lux,battery_pct,signal_dbm.

### POST api/simulate.php
Session-authenticated + CSRF. Generates one realistic reading and inserts it.

## Firmware
ESP32 sketch in device/ecosense_node.esp32.ino. Reads DHT22, BMP280, BH1750 with DeepSleep ~2s, Wi-Fi connect with retry, LED status, HTTP POST with X-Device-Key, OTA stub. See device/README.md.

## Demo data
Import database/schema.sql to create tables with seed data (4 devices, demo users, alert rules, ~2000 readings over 48h). Re-import to refresh.
