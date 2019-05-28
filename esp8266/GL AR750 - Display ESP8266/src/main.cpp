#include <Arduino.h>
String readString;

void setup()
{
  Serial.begin(115200);
  Serial.println("\nTest");
}


void loop () {
  //if(Serial.available() > 0)
    //Serial.write(Serial.read());

  while (true) {
    delay(1);  //delay to allow buffer to fill 
    if (Serial.available() > 0) {
      char c = Serial.read();  //gets one byte from serial buffer
      readString += c; //makes the string readString
      if (c == '\n') break;
    }
  }
  if (readString.length() > 0) {
    if (readString.length() >= 5 && readString.substring(0,3) == "ESP") {
      String json = readString.substring(3);
      Serial.println(json);
    } else {
      Serial.println(readString);
    }
    readString="";
  } 
}