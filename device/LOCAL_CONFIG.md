You can paste the complete sketch into a single Arduino IDE tab and edit the placeholder settings at the top. For a separate configuration file, copy its local configuration template instead:

- ESP8266: copy `esp8266_local.h.example` to `esp8266_local.h`.
- ESP32: copy `esp32_local.h.example` to `esp32_local.h`.

Set your Wi-Fi credentials, server URL, device UID and device API key in the local header. The local headers are ignored by Git so credentials stay on your computer. Keep the header in the same Arduino sketch folder when copying the sketch into Arduino IDE.
