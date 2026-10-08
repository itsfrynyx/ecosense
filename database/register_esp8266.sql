-- Run ONCE after choosing a private random 64-character hexadecimal API key.
-- Replace the placeholder below and use the SAME key in ecosense_esp8266.ino.
INSERT INTO devices (device_uid, name, location, api_key, firmware, status)
VALUES ('ECO-ESP8266-01', 'EcoSense NodeMCU', 'Sensor Station',
        'REPLACE_WITH_YOUR_64_CHARACTER_RANDOM_HEX_API_KEY', 'esp8266/1.0', 'offline');
-- If the UID already exists, edit that row's key instead of inserting again.
