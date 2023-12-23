![Chatrooms Satellite](https://github.com/PopularTopplingJelly/Chatrooms/blob/main/.webassets/WatermarkSatelliteEnabled128.png) (Art by GeofTheCake)
# Chatrooms
Chatrooms is a free, open source and lightweight chat platform where anyone can host a space for their friends, people and even family to hang out. Chatrooms has features like channels, server properties, and decent customizability. 

**NOTE: Chatrooms is still in very early development, if you wish to contribute be sure to open a pull request in the community branch!**

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
Make sure you have a mailserver set in your `php.ini` and an administrator email set in your `serverproperties.json` in order to use the email verification for your Chatroom!
### You're done! You just need to start the Satellite and your webserver to get your Chatroom up and running.
