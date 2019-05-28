# AR750 Hardware Modifications
One Paragraph of project description goes here

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

Fritzing Diagram
![Fritzing Diagram](https://i.imgur.com/dWSZMaj.png)

Current Project Progress:
![Current Project Progress](https://i.imgur.com/BX0IY3g.jpg)
### Idea

Having a `SSD1306 128x64 I²C Display` on my `GL.iNet AR750`. That displays the status of one or more 4G Modems.

Router Serial Output => ESP8266 Serial Input
Protocol is JSON


### Prerequisites

On the Router you need to have php7 and some modules installed

```
opkg update
opkg install php7 php7-cli php7-mod-json php7-mod-curl
```

### Installing

A step by step series of examples that tell you how to get a development env running

Say what the step will be

```
Give the example
```

And repeat

```
until finished
```

End with an example of getting some data out of the system or using it for a little demo

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details