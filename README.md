# AR750 Hardware Modifications
One Paragraph of project description goes here

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

Fritzing Diagram
![Fritzing Diagram](https://i.imgur.com/dWSZMaj.png)


### Idea

Having a `SSD1306 128x64 I²C Display` on my `GL.iNet AR750`. That displays the status of one or more 4G Modems.

Router Serial Output => ESP8266 Serial Input
Protocol is JSON

the ESP takes care of showing the bootlog and then information about the modem


### Current Project Progress
![Current Project Progress](https://github.com/cuddlycheetah/AR750-Hardware-Modifications/blob/readme-update-1/image.png?raw=true)



### Prerequisites

On the Router you need to have php7 and some modules installed
```
opkg update
opkg install php7 php7-cli php7-mod-json php7-mod-curl php7-mod-simplexml php7-mod-hash
```

### Installing
* Execute the PHP Script at boot via an command configured in the web interface

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
