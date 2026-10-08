# EcoSense Device Firmware

## Hardware
- ESP32-WROOM-32 development board
- DHT22 (AM2302) temperature/humidity sensor
- BMP280 (or BME280) barometric pressure sensor
- BH1750 ambient light sensor
- DS18B20 waterproof probe (optional - can be added)
- 18650 Li-ion + TP4056 charger (optional for battery operation)
- 2.4GHz Wi-Fi network

## Wiring
| Component | ESP32 Pin | Notes |
|-----------|-----------|-------|
| DHT22 | GPIO4 | Pullup 4.7kΩ recommended |
| BMP280/BME280 (I2C) | SDA=GPIO21, SCL=GPIO22 | 3.3V logic |
| BH1750 (I2C) | SDA=GPIO21, SCL=GPIO22 | 3.3V logic |
| DS18B20 (optional) | GPIO5 | 4.7kΩ pullup if used |
| Status LED | GPIO2 | Built-in LED |

## Required Libraries
Install via Arduino IDE Library Manager:
- DHT sensor library by Adafruit
- Adafruit Unified Sensor
- Adafruit BMP280 Library
- BH1750 by Christopher Laws
- ArduinoJson

## Configuration
Edit `ecosense_node.esp32.ino`:
- `WIFI_SSID` / `WIFI_PASS` - your network credentials
- `API_HOST` - IP/hostname of your XAMPP server (e.g., `http://192.168.1.100`)
- `API_PATH` - full path to `api/ingest.php`
- `DEVICE_UID` - must match a device in `devices` table
- `DEVICE_APIKEY` - must match the device's API key (64 hex chars)

## Operation
1. Connect ESP32 via USB
2. Open sketch in Arduino IDE
3. Configure Wi-Fi and API details
4. Upload to board
5. Open Serial Monitor (115200 baud) to view logs
6. Device posts readings every ~2 seconds, blinks LED on success/failure
7. In battery mode, uses DeepSleep for ~2 seconds

## OTA Stub
The sketch includes a minimal `update_http()` stub for over-the-air updates. Extend as needed for your deployment.

## Testing
After uploading, check `dashboard.php` or `api/latest.php` - readings should appear within a few seconds. The device updates `devices.last_seen_at` and sets status to `online` on successful ingest.