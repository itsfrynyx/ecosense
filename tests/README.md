# Verification

Run from the project root:

```powershell
C:\xamppni\php\php.exe tests/helpers.php
node tests/sensors.cjs
node tests/live.cjs
node tests/browser.cjs
.\tests\firmware.ps1
```

The helper and sensor tests run without MySQL. The sensor tests use the real endpoints with a mock database adapter.

Live and browser tests require Apache/MySQL and an existing migrated EcoSense database. They create temporary accounts and a node with a random TEST- prefix, then remove those records in cleanup. They do not change existing device credentials or readings. The browser test uses the installed Edge in headless mode with a temporary profile.

Override ECOSENSE_TEST_URL, PHP_BINARY or EDGE_BINARY if your installation paths differ. The firmware check uses the installed Arduino CLI and ESP8266 core, copies only the sketch and example configuration to a temporary directory, and compiles for NodeMCU. It does not upload to hardware. ESP32 compilation requires an independently installed ESP32 core.
