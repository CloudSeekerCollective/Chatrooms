# How to set up the Chatrooms Satellite
The Chatrooms Satellite is what handles the messages being sent and recieved in your Chatroom. Here you can find out how to set it up.
## PREREQUISITES
You can get most of these prerequisites (except for Ratchet) by downloading [XAMPP](https://apachefriends.org) (Not Sponsored)
- Ratchet [Get it here](http://socketo.me)
- PHP runtime
- MySQL server
## Set up the database!
In order to set up the Chatrooms Satellite, you firstly need to create a database in your MySQL server with name "chrms_universe".
Add 3 tables to it, ones with names `accounts`, `messages`, and `channels`.
### The accounts table
The accounts table is meant to consist of the columns 
- `username` (VARCHAR 50 characters) 
- `email` (VARCHAR 100 characters) 
- `password` (TEXT)
- `id` (INT or TEXT)
- `status` (VARCHAR 50 characters or TEXT)
- `userstatus` (VARCHAR 128 characters)
- `picture` (TEXT)
- `creationdate` (BIGINT)
- `authentication` (TEXT)
- `badges` (JSON or TEXT)
- `roles` (JSON or TEXT) 
- `2fa_admission` (BOOLEAN)
### The messages table
The messages table is meant to consist of the columns 
- `author` (INT or TEXT), 
- `content` (TEXT), 
- `channel` (anything you want), 
- `date` (INT), 
- `number` (INT), 
- `attachment1` (TEXT)
### The channels table
The channels table is meant to consist of the columns `id` (INT or TEXT), `name` (VARCHAR 20 characters or TEXT), and `allowed_roles` (JSON or TEXT)
## Once all of this is done, we need to get to setting up your Satellite
Your database is now all set! We need to get to some miscellaneous configurations now.
### SatelliteConfig.json
This file is a general configuration for the Satellite. Without it, the Satellite will not function. 
Here's what you need to set to get the Satellite up and running:
- "port": the port to be used for your Satellite
- "mysql_username": the MySQL username to be used
- "mysql_password": the MySQL password to be used
- "etc_configs_path": the client configuration that you set up earlier in the general setup
- "cert_path" and "pkey_path": paths to your SSL certificate and private key
- "debug_mode": self explanatory

**NOTE: Make sure that the configuration you set is named SatelliteConfig.json and is placed in the SAME LOCATION as the SATELLITE EXECUTABLE**
**You can download an example of the configuration [HERE](https://github.com/PopularTopplingJelly/Chatrooms/raw/main/satellite/SatelliteConfig_EXAMPLE.json)**
## Run the Satellite!
To run the Satellite, all you need to do is open up a terminal and run "php ./chatroomsSatellite.php". 
But wait, there's much more you need to do - pay a visit to the README.md file at the root of the repository to find out what to do next.
