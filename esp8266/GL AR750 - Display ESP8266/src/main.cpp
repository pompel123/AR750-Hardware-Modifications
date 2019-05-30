#include <Arduino.h>
#include <ArduinoJson.h>

#include <SPI.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET     -1
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

#define REMOTE_UPLOAD
#ifdef REMOTE_UPLOAD
  #include <ESP8266WiFi.h>
  #include <ArduinoOTA.h>

  #pragma region "Wifi Details" 

    const uint8_t bssid[6]  = { 0xB2, 0x48, 0x7A, 0x99, 0xD4, 0xDC }; // B0:48:7A:99:D4:DC
    //const uint8_t bssid[6]  = { 0xE6, 0x95, 0x6E, 0x43, 0x42, 0xE6 }; // E6:95:6E:43:42:E6

    char ssid[]             = "🐆📶🅳🅼🆉";
    char password[]         = "cuddlycheetah";

    IPAddress ip            (192, 168, 001, 250);
    IPAddress gateway       (192, 168,   1,   1);
    IPAddress subnet        (255, 255, 255,   0);
    IPAddress dns           (192, 168,   1,   1);

  #pragma endregion
void setupWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.config(ip, gateway, subnet, dns);
  WiFi.begin(ssid, password, 11, bssid);
  while (WiFi.waitForConnectResult() != WL_CONNECTED) {
    Serial.println("Connection Failed! Rebooting...");
    delay(5000);
    ESP.restart();
  }
  randomSeed(micros());
  Serial.println("Ready");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
}
void setupOTA() {
  ArduinoOTA.onStart([]() {
    String type;
    if (ArduinoOTA.getCommand() == U_FLASH) {
      type = "sketch";
    } else { // U_SPIFFS
      type = "filesystem";
    }

    // NOTE: if updating SPIFFS this would be the place to unmount SPIFFS using SPIFFS.end()
    Serial.println("Start updating " + type);
  });
  ArduinoOTA.onEnd([]() {
    Serial.println("\nEnd");
  });
  ArduinoOTA.onProgress([](unsigned int progress, unsigned int total) {
    Serial.printf("Progress: %u%%\r", (progress / (total / 100)));
  });
  ArduinoOTA.onError([](ota_error_t error) {
    Serial.printf("Error[%u]: ", error);
    if (error == OTA_AUTH_ERROR) {
      Serial.println("Auth Failed");
    } else if (error == OTA_BEGIN_ERROR) {
      Serial.println("Begin Failed");
    } else if (error == OTA_CONNECT_ERROR) {
      Serial.println("Connect Failed");
    } else if (error == OTA_RECEIVE_ERROR) {
      Serial.println("Receive Failed");
    } else if (error == OTA_END_ERROR) {
      Serial.println("End Failed");
    }
  });
  ArduinoOTA.begin();
}
#endif

void wakeOLED() {
  display.ssd1306_command(SSD1306_DISPLAYON);
  display.ssd1306_command(SSD1306_SETVCOMDETECT);
  display.ssd1306_command(0x40);
  display.ssd1306_command(SSD1306_SETCONTRAST);
  display.ssd1306_command(0xFF);
}
void oledPowersave1() {
  display.ssd1306_command(SSD1306_SETVCOMDETECT);
  display.ssd1306_command(0x00);
  display.ssd1306_command(SSD1306_SETCONTRAST);
  display.ssd1306_command(0x00);
}
void oledPowersave2() {
  display.ssd1306_command(SSD1306_DISPLAYOFF);
}
String readString;

struct StatusObject {
  char Error[64];
  int Offline;
  int Connection_Status;
};

String backLog[6];
void setup() {
  backLog[3] = "";
  backLog[2] = "";
  backLog[1] = "";
  backLog[0] = "";

  Serial.begin(115200);
  Serial.println("Starting\n");
  
  display.begin(0x02, 0x3C);
  display.setRotation(2);

  display.clearDisplay();
  display.display();
  display.setTextColor(1);
  display.ssd1306_command(SSD1306_SETVCOMDETECT);
  display.ssd1306_command(0x00);
  display.ssd1306_command(SSD1306_SETCONTRAST);
  display.ssd1306_command(0x01);

  #ifdef REMOTE_UPLOAD
    setupWifi();
    setupOTA();
  #endif
}

StaticJsonDocument<1024> packet;
JsonObject modemInfo;

uint8_t displayMode = 0;
void display0() {
  display.setTextWrap(true);
  display.setCursor(0, 0);
  display.println(backLog[5]);
  display.println(backLog[4]);
  display.println(backLog[3]);
  display.println(backLog[2]);
  display.println(backLog[1]);
  display.println(backLog[0]);
}
#include <Fonts/FreeMono9pt7b.h>
void display1signalbars(int x, int y) {
  uint8_t signalIcon = modemInfo["Signal"]["Icon"].as<uint8_t>();
  uint8_t h = 28;

  for (uint8_t i = 0; i < 5; i++) { // Signalbalken
    uint8_t bw = 6;
    uint8_t bh = 8 + ((uint8_t)(h / 5) * i);
    uint8_t bx = 8 * i;
    uint8_t by = h - bh;

    if (signalIcon > i + 1)
      display.fillRect(x + bx, y + by, bw, bh, 1);
    else
      display.drawRect(x + bx, y + by, bw, bh, 1);
  }
  if (modemInfo["Network"]["Roaming"].as<bool>() == true) { // Roaming Indikator
    display.setTextSize(2);
    display.setCursor(x, y);
    display.println("R");
    display.setTextSize(1);
  }
}
void display1() {
  display.setTextWrap(true);
  display.setFont(NULL/*&FreeMono9pt7b*/);

  display1signalbars(0, 0);

  String networkName  = modemInfo["Network"]["Name"].as<String>();
  String networkTypeG = modemInfo["Network"]["Type"]["G"].as<String>();
  String networkType_ = modemInfo["Network"]["Type"]["_"].as<String>();
  String networkIP    = modemInfo["Network"]["IP"].as<String>();
  int signalRSSI = modemInfo["Signal"]["RSSI"].as<int>();

  display.setCursor(44,  0); display.println(networkName);
  display.setCursor(44, 10); display.printf("%s %s\n", &networkTypeG, &networkType_);
  display.setCursor(44, 20); display.printf("%d dBm\n", signalRSSI);
  display.setCursor(0, 30); display.printf("IP: %s\n", &networkIP);
}
void loop () {
  display.clearDisplay();
  display.drawPixel(127,0,1);
  display.drawPixel(127,31,1);
  display.drawPixel(0,31,1);

  switch (displayMode) {
    case 0: display0(); break;
    case 1: display1(); break;
  }
  display.display();
  
  #ifdef REMOTE_UPLOAD
    ArduinoOTA.handle();
  #endif

  readString = Serial.readStringUntil('\n');
  int rLength = readString.length();
  if (rLength > 0) {
    if (rLength >= 5 && readString.substring(0,3) == "ESP") {
      displayMode = displayMode == 0 ? 1 : displayMode;

      if (rLength >= 10 && readString.substring(0,8) == "ESPMODEM") {
        String json = readString.substring(8);
        DeserializationError error = deserializeJson(packet, json);
        if (error) {
          Serial.print(F("deserializeJson() failed: "));
          Serial.println(error.c_str());
          Serial.println(json);
          readString="";
          return;
        }
        modemInfo = packet.as<JsonObject>();
        serializeJsonPretty(packet, Serial);
      } else {
        String def = readString.substring(3);
        Serial.println(def);
      }
    } else {
      Serial.println(readString);
      String lastLog = readString;
      if (lastLog.indexOf(']') > 0)
        lastLog = lastLog.substring(lastLog.indexOf(']') + 1);
      if (lastLog.indexOf('  ', 3) == 0)
        lastLog = lastLog.substring(1);
      
      backLog[5] = backLog[4];
      backLog[4] = backLog[3];
      backLog[3] = backLog[2];
      backLog[2] = backLog[1];
      backLog[1] = backLog[0];
      backLog[0] = lastLog;

    }
    readString="";
  } 
}