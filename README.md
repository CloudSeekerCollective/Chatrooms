![Chatrooms Satellite](https://github.com/PopularTopplingJelly/Chatrooms/blob/main/.webassets/WatermarkSatelliteEnabled128.png) (Art by GeofTheCake)
# Chatrooms
Chatrooms is a free, open source and lightweight chat platform where anyone can host a space for their friends, people and even family to hang out. Chatrooms has features like channels, server properties, and decent customizability. 

**NOTE: Chatrooms is still in very early development, if you wish to contribute be sure to open a pull request in the community branch!**

Web and desktop client source code coming soon!
## How to setup
**NOTE: You need to follow the [Satellite Setup Guide](https://github.com/PopularTopplingJelly/Chatrooms/blob/main/satellite/how_to_satellite.md) first. Once you do that, you can continue to follow this guide.**
### Initial setup
Clone the repository in the parent folder of the webserver root.
### Server-side configuration
Go to `/api/chatrooms`, and place the `connect.php` example in there. Configure everything you need to get everything up and running.
You may also need to create a folder named "userstore" in the webserver root.
### Client-side configuration
Go to the root of your webserver and place the `serverproperties.json` example in there. Again, configure everything you need to get everything up and running.
### Email configuration
![Headers](https://github.com/PopularTopplingJelly/Chatrooms/blob/main/.webassets/2023-01-01_14-26.png?raw=true)

You need to change this line in `/chatrooms/login/step1/index.php` and `/chatrooms/login/step1/index.php` with the email you want to use to send emails. Make sure you have a mailserver set in your php.ini before using the email functionality!
### You're done! You just need to start the Satellite and your webserver to get your Chatroom up and running.
