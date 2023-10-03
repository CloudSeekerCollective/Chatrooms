<?php
set_time_limit(0);

use Ratchet\Session\SessionProvider;
use Symfony\Component\HttpFoundation\Session\Storage\Handler;
use Ratchet\App;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server;
use React\Socket\SecureServer;
require_once '../bin/vendor/autoload.php';

class Chatroom implements MessageComponentInterface {
	protected $clients;
	protected $users;

	public function __construct() {
		$GLOBALS['redeclaration'] = false;
		echo("Welcome to the Chatrooms Experience!\n
      Chatrooms is a free, open source and lightweight chat platform where anyone can host a space for their friends, people and even family to hang out.
    Copyright (C) 2022-2023 The CloudSeeker Collective  (Theodor Boshkoski, GeofTheCake and OreyTV) <https://cloudseeker.xyz>\n

        This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published
    by the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.\n");
		$this->clients = new \SplObjectStorage;
		echo("[Satellite] Server started\n");
		sleep(1);
		$configpath = file_get_contents("./SatelliteConfig.json");
		$config = json_decode($configpath, true);
		try{
			$ctds = mysqli_connect("localhost", $config['mysql_username'], $config['mysql_password'], "chrms_universe");
			$reset_online_users = mysqli_query($ctds, "UPDATE `accounts` SET `is_online`='0' WHERE 1");
			$check_for_new_cols_1 = mysqli_query($ctds, "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `user_public_mood` VARCHAR(20)");
			$check_for_new_cols_2 = mysqli_query($ctds, "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `presence` VARCHAR(10)");
		}
		catch(\Exception $e){
			echo("ERROR! " . $e ."\nThe Chatrooms Satellite cannot continue without the database functioning. This is either because your database server is not on, or because invalid credentials were provided - please check the configuration and your DB server\nAbort.\n");
			exit;
		}
	}

	public function onOpen(ConnectionInterface $conn) {
		$configpath = file_get_contents("./SatelliteConfig.json");
		$config = json_decode($configpath, true);
		$ctds = mysqli_connect("localhost", $config['mysql_username'], $config['mysql_password'], "chrms_universe");
		$this->clients->attach($conn);
		$scpath = file_get_contents($config['etc_configs_path']);
		$serverconfig = json_decode($scpath, true);
		echo("[Satellite] New connection\n");
		// if the user didnt provide any authentication...
		if(empty($conn->httpRequest->getUri()->getQuery()) and $serverconfig['allow_anonymous'] == false)
		{
			echo("[Satellite] 1User did not provide authentication! Disconnecting.\n");
			$conn->send('{"status":"fail", "error":"no_authentication_provided"}');
			$this->clients->detach($conn);
			$conn->close();
		}
		// if the user did, then do all of this
		else{
			// if the client is just checking if the server is available...
			if(substr(stripslashes(htmlspecialchars($conn->httpRequest->getUri()->getQuery())), 5) == "ping"){
				$conn->send("[ChatroomsPing] Hello!");
				$this->clients->detach($conn);
				$conn->close();
			}
			// if the server is localhost-locked, disconnect them lol
			if($serverconfig['localhost_lock'] == true)
			{
				echo("[Satellite] Chatroom is localhost-locked! Disconnecting.\n");
				$conn->send('{"status":"fail", "error":"localhost_lock_enabled"}');
				$this->clients->detach($conn);
				$conn->close();
			}
			// check if the user is trying to trick the server
			if(!empty($conn->httpRequest->getUri()->getQuery())){
				// isolate the user creds
				$utoken = substr(stripslashes(htmlspecialchars($conn->httpRequest->getUri()->getQuery())), 5);

				if($utoken == "Anonymous"){
					$conn->send("Welcome to the Chatrooms Experience!");
					return;
				}

				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `2fa_admission`, `authentication`, `is_online`, `status` FROM `accounts` WHERE `authentication`='". $utoken ."'");
	
				// if there is no error...
				if(!is_bool($lfdu)){
					// if a user matching the ID exists...
					if(mysqli_num_rows($lfdu) != 0){
						// cache db result
						$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
						// if the token found in the database matches the one provided...
						if(stripslashes(htmlspecialchars($lfdu_RSLT['authentication']) == $utoken)){
							// if the user is not restricted, continue
							if($lfdu_RSLT['status'] != "RESTRICTED"){
								// if the user is banned, stop
								if($lfdu_RSLT['status'] == "BANNED"){							
									echo("[Satellite] 1User is banned! Disconnecting.\n");
									$conn->send('{"status":"fail", "error":"or_you_will_get_clapped"}');
									$this->clients->detach($conn);
									$conn->close();
								}
								// TODO: make sure the user isnt already online
								//if($lfdu_RSLT['is_online'] != "1"){
									// if the server has email verification...
									if($serverconfig['require_email'] == true){
										// ...check if the user has verified email
										if($lfdu_RSLT['2fa_admission'] == "1"){
											//$this->users->attach("{'username':'". $lfdu_RSLT['username'] ."', 'id':'". $lfdu_RSLT['id'] ."', 'token':'". $utoken ."'}");
											$conn->send("Welcome to the Chatrooms Experience!");
											// allow the user to join
											foreach($this->clients as $client) {
												if($conn!=$client) {
													$client->send('{"action":"join","status":"success", "user":"'. stripslashes(htmlspecialchars($lfdu_RSLT['username'])) .'", "channel":"'. $serverconfig['system_channel'] .'", "uid":"'. stripslashes(htmlspecialchars($lfdu_RSLT['id'])) .'", "msg":"has joined the Chat!","time":"'. time() .'", "attachment1":""}');
												}
											}
											// make them appear as online
											$reset_online_users = mysqli_query($ctds, "UPDATE `accounts` SET `is_online`='1' WHERE `authentication`='". $utoken ."'");
										}
										else{
											// disconnect if not
											echo("[Satellite] 1User hasn't completed their profile! Disconnecting.\n");
											$conn->send('{"status":"fail", "error":"email_not_set"}');
											$this->clients->detach($conn);
											$conn->close();
										}
									}
									else{
										$conn->send("Welcome to the Chatrooms Experience!");
										//$this->users->attach("{'username':'". $lfdu_RSLT['username'] ."', 'id':'". $lfdu_RSLT['id'] ."', 'token':'". $utoken ."'}");
										// if email is not required, let them in
										foreach($this->clients as $client) {
											if($conn!=$client) {
												$client->send('{"action":"join","status":"success", "user":"'. stripslashes(htmlspecialchars($lfdu_RSLT['username'])) .'", "channel":"'. $serverconfig['system_channel'] .'", "uid":"'. stripslashes(htmlspecialchars($lfdu_RSLT['id'])) .'", "msg":"has joined the Chat!","time":"'. time() .'", "attachment1":""}');
											}
										}
										// make them appear as online
										//$reset_online_users = mysqli_query($ctds, "UPDATE `accounts` SET `is_online`='1' WHERE `authentication`='". $utoken ."'");
									}
								/*}
								else{
									echo("[Satellite] 1User already online! Disconnecting.\n");
									$conn->send('{"status":"fail", "error":"user_already_online"}');
									$this->clients->detach($conn);
									$conn->close();
								}*/
							}
							else{
								echo("[Satellite] 1User is disallowed to join the Chatroom for now! Disconnecting.\n");
								$conn->send('{"status":"fail", "error":"account_temporarily_restricted"}');
								$this->clients->detach($conn);
								$conn->close();
							}
						}
						else{
							// disconnect user if there is no token provided
							echo("[Satellite] 2User did not provide authentication! Disconnecting.\n");
							$conn->send('{"status":"fail", "error":"no_authentication_provided"}');
							$this->clients->detach($conn);
							$conn->close();
						}
					}
					else{
						// disconnect user if there is no token provided
						echo("[Satellite] 3User did not provide authentication! Disconnecting.\n");
						$conn->send('{"status":"fail", "error":"no_authentication_provided"}');
						$this->clients->detach($conn);
						$conn->close();
					}
				}
				// otherwise...
				else{
					// disconnect user if there is no token provided
					echo("[Satellite] 4User did not provide authentication! Disconnecting.\n");
					$conn->send('{"status":"fail", "error":"no_authentication_provided"}');
					$this->clients->detach($conn);
					$conn->close();
				}	
			}
		}
	}

	public function onClose(ConnectionInterface $conn) {
		$configpath = file_get_contents("./SatelliteConfig.json");
		$config = json_decode($configpath, true);
		$ctds = mysqli_connect("localhost", $config['mysql_username'], $config['mysql_password'], "chrms_universe");
		$this->clients->detach($conn);
		echo("[Satellite] Someone disconnected :(\n");
		$scpath = file_get_contents($config['etc_configs_path']);
		$serverconfig = json_decode($scpath, true);
		// if the user didnt provide any authentication...
		if(empty($conn->httpRequest->getUri()->getQuery()) and $serverconfig['allow_anonymous'] == false)
		{
			echo("[Satellite] 1User did not provide authentication! Disconnecting.\n");
			$conn->send('{"status":"fail", "error":"no_authentication_provided"}');
			$this->clients->detach($conn);
			$conn->close();
		}
		// if the user did, then do all of this
		else{
			// if the client is just checking if the server is available...
			if($conn->httpRequest->getUri()->getQuery() == "ping"){
				$conn->send("[ChatroomsPing] Hello!");
				$this->clients->detach($conn);
				$conn->close();
			}
			// if the server is localhost-locked...
			if($serverconfig['localhost_lock'] == true)
			{
				echo("[Satellite] Chatroom is localhost-locked! Disconnecting.\n");
				$conn->send('{"status":"fail", "error":"localhost_lock_enabled"}');
				$this->clients->detach($conn);
				$conn->close();
			}
			// check if the user is trying to trick the server
			if(!empty($conn->httpRequest->getUri()->getQuery())){
				// isolate the user creds
				$utoken = substr(stripslashes(htmlspecialchars($conn->httpRequest->getUri()->getQuery())), 5);

				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `2fa_admission`, `authentication`, `is_online`, `status` FROM `accounts` WHERE `authentication`='". $utoken ."'");
	
				// if there is no error...
				if(!is_bool($lfdu)){
					// if a user matching the ID exists...
					if(mysqli_num_rows($lfdu) != 0){
						// cache db result
						$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
						// if the token found in the database matches the one provided...
						if(stripslashes(htmlspecialchars($lfdu_RSLT['authentication']) == $utoken)){
							// if the user is not restricted, continue
							if($lfdu_RSLT['status'] != "RESTRICTED"){
								// if the user is banned, stop
								if($lfdu_RSLT['status'] == "BANNED"){							
									echo("[Satellite] 1User is banned! Oh well.\n");
								}
								// make sure the user is online
								//if($lfdu_RSLT['is_online'] == "1"){
									// if the server has email verification...
									if($serverconfig['require_email'] == true){
										// ...check if the user has verified email
										if($lfdu_RSLT['2fa_admission'] == "1"){
											//$this->users->detach("{'username':'". $lfdu_RSLT['username'] ."', 'id':'". $lfdu_RSLT['id'] ."', 'token':'". $utoken ."'}");
											// announce departure
											foreach($this->clients as $client) {
												if($conn!=$client) {
													$client->send('{"action":"leave","status":"success", "user":"'. stripslashes(htmlspecialchars($lfdu_RSLT['username'])) .'", "channel":"'. $serverconfig['system_channel'] .'", "uid":"'. stripslashes(htmlspecialchars($lfdu_RSLT['id'])) .'", "msg":"has left the Chat :(","time":"'. time() .'", "attachment1":""}');
												}
											}
											// make user appear as offline
											$reset_online_users = mysqli_query($ctds, "UPDATE `accounts` SET `is_online`='0' WHERE `authentication`='". $utoken ."'");
										}
										else{
											echo("[Satellite] 1User hasn't completed their profile! Oh well.\n");
										}
									}
									else{
										
										foreach($this->clients as $client) {
											if($conn!=$client) {
												$client->send('{"action":"leave","status":"success", "user":"'. stripslashes(htmlspecialchars($lfdu_RSLT['username'])) .'", "channel":"'. $serverconfig['system_channel'] .'", "uid":"'. stripslashes(htmlspecialchars($lfdu_RSLT['id'])) .'", "msg":"has left the Chat :(","time":"'. time() .'", "attachment1":""}');
											}
										}
										$reset_online_users = mysqli_query($ctds, "UPDATE `accounts` SET `is_online`='0' WHERE `authentication`='". $utoken ."'");
									}
								/*else{
									echo("[Satellite] 1User already online! Oh well.\n");
								}*/
							}
							// if the user IS restricted...
							else{
								echo("[Satellite] 1User is disallowed to join the Chatroom for now! Oh well.\n");
							}
						}
						// if it doesnt...
						else{
							// disconnect user if there is no token provided
							echo("[Satellite] 2User did not provide authentication! Oh well.\n");
						}
					}
					else{
						// disconnect user if there is no token provided
						echo("[Satellite] 3User did not provide authentication! Oh well.\n");
					}
				}
				// otherwise...
				else{
					// disconnect user if there is no token provided
					echo("[Satellite] 4User did not provide authentication! Oh well.\n");
				}	
			}
		}
	}

	public function onMessage(ConnectionInterface $from,  $data) {
		// get satellite configuration
		$configpath = file_get_contents("./SatelliteConfig.json");
		// parse it
		$config = json_decode($configpath, true);
		// connect to the database
		$ctds = mysqli_connect("localhost", $config['mysql_username'], $config['mysql_password'], "chrms_universe");
		echo("[Satellite] Message recieved\n");
		// get client configs
		$scpath = file_get_contents($config['etc_configs_path']);
		// parse that too
		$serverconfig = json_decode($scpath, true);
		// data sent from the client is MOST LIKELY json, so parse it
		$dataset = json_decode($data, true);
		switch(stripslashes(htmlspecialchars($dataset['type']))){
			case "message":
				//extraStuff/message_intent();
				// if theres a message and/or an attachment...
		if(!empty($dataset['message']) or !empty($dataset['attachment1'])){
			// DEPRECATED: connectionlist will be removed in a future release
			/*if($dataset['message'] == "/SATELLITE connectionlist"){
				echo(var_dump($this->clients));
			}
			elseif($dataset['message'] == "/SATELLITE connectionlist write"){
				//echo(var_dump($this->clients));
				echo("[Satellite] Writing connection list! \n");
				//file_put_contents("../htdocs/connectionlist.txt", $this->clients);
				$myfile = fopen("../htdocs/connectionlist.txt", "w") or die("Unable to open file!");
				$txt = var_dump($this->clients);
				fwrite($myfile, $txt);
				fclose($myfile);
			}*/
			// isolate information
			$mesg = stripslashes(htmlspecialchars($dataset['message']));
			$chnl = stripslashes(htmlspecialchars($dataset['channel']));
			$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
						// cache db results
						$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
						$lfdc = mysqli_query($ctds, "SELECT * FROM `channels` WHERE `id`='". $chnl ."'");
						$lfdc_RSLT = mysqli_fetch_assoc($lfdc);
						$channel_allowed = json_decode($lfdc_RSLT['allowed_roles']);
						$userroles = json_decode($lfdu_RSLT['roles']);
						$greenlight = false;
						// isolate username, user ID
						$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
						$id = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
						$emkeyscount = 0;
						$actual_mesg = $mesg;
						$mid = mt_rand(10000001, 99999999);
							// COMING SOON, will be correctly implemented in a future release
							if(!empty($channel_allowed[0])){
								for($i = 0; $i >= $userroles; $i++){
									if($userroles[$i] == $channel_allowed){
										$greenlight = true;
									}
									else{
										$greenlight = false;
									}
								}
							}
							else{
								$greenlight = true;
							}
							if($greenlight == true){
								// if the result is successful...
								$from->send('{"action":"message","status":"success", "user":"'. $usrnm .'", "channel":"'. $chnl .'", "uid":"'. $id .'", "msg":"' .  $actual_mesg . '","time":"'. time() .'","msgid":"'. $mid .'","attachment1":"'. $attach1 .'"}');
								if($lfdu_RSLT['status'] != "STAGING"){
									if($serverconfig['save_messages'] == true){
										// insert into 'messages' table
										echo("[Satellite] Message saved\n");
										$query = "INSERT INTO `messages`(`author`, `content`, `channel`, `date`, `number`, `attachment1`) VALUES ('". $id ."',  '". $actual_mesg ."', '". $chnl ."', '". time() ."', '". $mid ."', '". $attach1 ."')";  
										// TODO implement this: DELETE FROM `messages` WHERE `number`='512';   
		        							$submit = mysqli_query($ctds, $query);
									}
								}
								foreach($this->clients as $client) {
									if($from!=$client) {
										$utoken = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
										$lfduSEND = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $utoken ."'");
										$lfduS_RSLT = mysqli_fetch_assoc($lfduSEND);
										$userrolesS = json_decode($lfduS_RSLT['roles']);
										for($i = 0; $i >= $userrolesS; $i++){
											echo($userrolesS . " " . $channel_allowed . "\n");
											if($userrolesS[$i] == $channel_allowed){
												$greenlight = true;
											}
											else{
												$greenlight = false;
											}
										}
										if($greenlight == true){
											$client->send('{"action":"message","status":"success", "user":"'. $usrnm .'", "channel":"'. $chnl .'", "uid":"'. $id .'", "msg":"' .  $actual_mesg . '","time":"'. time() .'","msgid":"'. $mid .'","attachment1":"'. $attach1 .'"}');
											echo("[Satellite] Message by ". stripslashes(htmlspecialchars($usrnm)) ." successfully sent!\n");
										}
										else{
											// do nothing. lol
											echo("");
										}
									}
								}
							}
							else{
								echo("");
							}
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}
		
			break;
			case "channel":
				//extraStuff/message_intent();
				// if theres a message and/or an attachment...
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
						// cache db results
						$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
						$lfdc = mysqli_query($ctds, "SELECT * FROM `channels`");
						$lfdc_RSLT = mysqli_fetch_assoc($lfdc);
						$channel_allowed = json_decode($lfdc_RSLT['allowed_roles']);
						if(empty($lfdu_RSLT['roles'])){
							$userroles = "[]";
						}
						else{
							$userroles = json_decode($lfdu_RSLT['roles']);
						}
						$greenlight = false;
						// isolate username, user ID
						$nm = stripslashes(htmlspecialchars($lfdc_RSLT['name']));
						$id = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
						$emkeyscount = 0;
						$mid = mt_rand(10000001, 99999999);
						/* DEPRECATED: server-side emote processing, will be removed in a future release
						foreach($emotelist as $emotelist2){
							$emotekeys = array_keys($emotelist);
							if($emkeyscount == 0){
								$actual_mesg = str_replace($emotekeys[$emkeyscount], addslashes('<img src="'. stripslashes(htmlspecialchars($emotelist2)) .'" width="32" height="32">'), $mesg);
							}
							else{
								$actual_mesg = str_replace($emotekeys[$emkeyscount], addslashes('<img src="'. stripslashes(htmlspecialchars($emotelist2)) .'" width="32" height="32">'), $actual_mesg);
							}
							$emkeyscount++;
						}*/
							// COMING SOON, will be correctly implemented in a future release
							$greenlight = true;
							if($greenlight == true){
								// if the result is successful...
								// $from->send('{"action":"message","status":"success", "user":"'. $usrnm .'", "channel":"'. $chnl .'", "uid":"'. $id .'", "msg":"' .  $actual_mesg . '","time":"'. time() .'","msgid":"'. $mid .'","attachment1":"'. $attach1 .'"}');
								if($lfdu_RSLT['status'] != "STAGING"){
									if($serverconfig['save_messages'] == true){
										// insert into 'messages' table
										echo("[Satellite] ???\n");
									}
								}
					
								// if there is no error...
								if(!is_bool($lfdc)){
									$from->send('{"action":"channel","status":"success", "name":"'. stripslashes(htmlspecialchars($lfdc_RSLT['name'])) .'", "id":"'. stripslashes(htmlspecialchars($lfdc_RSLT['id'])) .'"}');
									$rewind = 1;				
									while($row = mysqli_fetch_assoc($lfdc)) {
										$actualid = stripslashes(htmlspecialchars($row['id']));
										$name = stripslashes(htmlspecialchars($row['name']));
										$from->send('{"action":"channel","status":"success", "name":"'. $name .'", "id":"'. $actualid .'"}');
										$rewind++;
			  						}						
								}
								/*foreach($lfdc_RSLT as $channel) {
									$from->send('{"action":"channel","status":"success", "name":"'. stripslashes(htmlspecialchars($channel['name'])) .'", "id":"'. stripslashes(htmlspecialchars($channel['id'])) .'"}');
								}*/
							}
							else{
								echo("");
							}
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
		
			break;
			case "login":
			// if authentication is NOT set, but a username and password are...
			if(empty($dataset['authentication']) and !empty($dataset['username']) and !empty($dataset['password'])){
				// isolate them
				$username = stripslashes(htmlspecialchars($dataset['username']));
				$password = $dataset['password'];
				// time to check
				$lfdu = mysqli_query($ctds, 'SELECT * FROM `accounts` WHERE `username`="'. $username .'"');
				// cache db results
				$strings = mysqli_fetch_assoc($lfdu);
				// ???
				$attempts = 1;
				// does the password given by the user match the hash?
				if(mysqli_num_rows($lfdu) > 0 and password_verify($password, $strings['password'])){
					// yes? good. give them the token
					$from->send('{"token":"' . $strings['authentication'] . '"}');
				}
				// no? tell them to back off
				else{
					$from->send('{"status":"fail", "error":"incorrect_credentials"}');
				}
			}
			break;
			case "older_messages":
				//extraStuff/message_intent();
				// if theres a message and/or an attachment...
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication']) and !empty($dataset['channel']) and $serverconfig['save_messages'] == true){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdc = mysqli_query($ctds, "SELECT * FROM `messages` WHERE `channel`='". $dataset['channel'] ."' ORDER BY `messages`.`date` DESC LIMIT 256");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdc)){
					// if the authentication matches a user...
					if(mysqli_num_rows($lfdc) != 0){
						// cache db results
						$lfdc_RSLT = mysqli_fetch_assoc($lfdc);
						$emkeyscount = 0;
						$mid = mt_rand(10000001, 99999999);
							// COMING SOON, will be correctly implemented in a future release
							$greenlight = true;
							if($greenlight == true){
								// if there is no error...
								if(!is_bool($lfdc)){
									$first_authoruname = "";
									$lfdc_2 = mysqli_query($ctds, "SELECT `username` FROM `accounts` WHERE `id`='". $lfdc_RSLT['author'] ."'");
									$lfdc_2_RSLT = mysqli_fetch_assoc($lfdc_2);
									if(mysqli_num_rows($lfdc_2) == 1){
										$first_authoruname = $lfdc_2_RSLT['username'];
									}
									// otherwise just use "Unknown User" xd
									else{
										$first_authoruname = "Unknown User";
									}
									$returnjson = array(array("id" => $lfdc_RSLT['number'], "author" => $lfdc_RSLT['author'], "channel" => $lfdc_RSLT['channel'], "content" => stripslashes(htmlspecialchars($lfdc_RSLT['content'])), "date" => $lfdc_RSLT['date'], "username" => $first_authoruname, "attachment1" => $lfdc_RSLT['attachment1']));
									//$from->send('{"action":"message","status":"success", "user":"'. $first_authoruname .'", "channel":"'. $lfdc_RSLT['channel'] .'", "uid":"'. $lfdc_RSLT['author'] .'", "msg":"' .  $lfdc_RSLT['content'] . '","time":"'. $lfdc_RSLT['date'] .'","msgid":"'. $lfdc_RSLT['number'] .'","attachment1":"'. $lfdc_RSLT['attachment1'] .'"}');
									$rewind = 1;				
									while($row = mysqli_fetch_assoc($lfdc)) {
										$author = stripslashes(htmlspecialchars($row['author']));
										$channl = stripslashes(htmlspecialchars($row['channel']));
										$contnt = stripslashes(htmlspecialchars($row['content']));
										$datumo = stripslashes(htmlspecialchars($row['date']));
										$attach1 = stripslashes(htmlspecialchars($row['attachment1']));
										$actualid = stripslashes(htmlspecialchars($row['number']));
										// look for a username...
										$lfdu_2 = mysqli_query($ctds, "SELECT `username` FROM `accounts` WHERE `id`='". $author ."'");
										$lfdu_2_RSLT = mysqli_fetch_assoc($lfdu_2);
										// if the account exists, use the username
										if(mysqli_num_rows($lfdu_2) == 1){
											$authoruname = $lfdu_2_RSLT['username'];
										}
										// otherwise just use "Unknown User" xd
										else{
											$authoruname = "Unknown User";
										}
										
										$returnjson[$rewind] = array(
											"id" => $actualid, 
											"author" => $author, 
											"channel" => $channl, 
											"content" => $contnt, 
											"date" => $datumo, 
											"username" => $authoruname, 
											"attachment1" => $attach1
									       );
										$rewind++;
										//$from->send('{"action":"message","status":"success", "user":"'. $authoruname .'", "channel":"'. $channl .'", "uid":"'. $author .'", "msg":"' .  $contnt . '","time":"'. $datumo .'","msgid":"'. $actualid .'","attachment1":"'. $attach1 .'"}');
									}
									$from->send('{"action":"older_messages","status":"success", "messages":'. json_encode($returnjson) .'}');
								}
								/*foreach($lfdc_RSLT as $channel) {
									$from->send('{"action":"channel","status":"success", "name":"'. stripslashes(htmlspecialchars($channel['name'])) .'", "id":"'. stripslashes(htmlspecialchars($channel['id'])) .'"}');
								}*/
							}
							else{
								echo("");
							}
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
		
			break;
			case "properties":
				// if theres a message and/or an attachment...
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));
				//var_dump($serverconfig);
				try{
					$from->send('{"action":"properties", 
						"xstatus":"success", 
						"server_name":"'. $serverconfig['server_name'] .'", 
						"welcome_message":"'. $serverconfig['welcome_message'] .'", 
						"save_messages":"'. $serverconfig['save_messages'] .'", 
						"system_channel":"'. $serverconfig['system_channel'] .'", 
						"load_all_history_if_any":"'. $serverconfig['load_all_history_if_any'] .'", 
						"content_id":"'. $serverconfig['content_id'] .'",
						"require_email":"'. $serverconfig['require_email'] .'",
						"allow_registrations":"'. $serverconfig['allow_registrations'] .'",
						"filesize_limit":"'. $serverconfig['filesize_limit'] .'",
						"chatrooms_distro":"'. $serverconfig['chatrooms_distro'] .'",
						"satellite_version":"0.8",
						"emotes":'. json_encode($serverconfig['emotes']) .'}');
				}
				catch(Exception $e){
					echo("\n** ERROR! Your chatroom is not set up properly! Make sure you have updated your server properties to match newest version's requirements. **\n
					Here's what went wrong:\n". var_dump($e));
					exit;
				}
			}
		
			break;
			case "user":
				if(!empty($dataset['authentication']) and !empty($dataset['id'])){
		// isolate authentication
		$auth = stripslashes(htmlspecialchars($dataset['authentication']));

		// lfdu = look for da user
		$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
			
		// if the result is NOT a boolean (in other words an error)...
		if(!is_bool($security_lfdu)){
			// if the authentication matches a user...
			if(mysqli_num_rows($security_lfdu) != 0){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	

				// lfdu = look for da user
				$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
					
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($security_lfdu)){
					// if the authentication matches a user...
					if(mysqli_num_rows($security_lfdu) != 0){
						// isolate the user ID
						$uid = stripslashes(htmlspecialchars($dataset['id']));
		
						// lfdu = look for da user
						$lfdu = mysqli_query($ctds, "SELECT `username`, `picture`, `id`, `status`, `profilestatus`, `creationdate`, `user_public_mood`, `presence` FROM `accounts` WHERE `id`='". $uid ."'");
						
						// if there is no error...
						if(!is_bool($lfdu)){
							// if a user matching the ID exists...
							if(mysqli_num_rows($lfdu) != 0){
								// check if the user is ACTUALLY online in the first place
								$pres = "cloaked";
								foreach($this->clients as $client) {
									if($from==$client) {
										$utoken = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
										$lfduSEND = mysqli_query($ctds, "SELECT `username`, `id`, `presence` FROM `accounts` WHERE `authentication`='". $utoken ."'");
										$lfduS_RSLT = mysqli_fetch_assoc($lfduSEND);
										$upresence = stripslashes(htmlspecialchars($lfduS_RSLT['presence']));
										$pres = $upresence;
									}
								}
								// cache db result
								$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
								// isolate all of these variables JUST IN CASE
								$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
								$mood = stripslashes(htmlspecialchars($lfdu_RSLT['user_public_mood']));
								$actualuid = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
								$stts = stripslashes(htmlspecialchars($lfdu_RSLT['status']));
								$pfp = stripslashes(htmlspecialchars($lfdu_RSLT['picture']));
								$ustts = "". stripslashes(htmlspecialchars($lfdu_RSLT['profilestatus']));
								$cdate = /*stripslashes(htmlspecialchars(gmdate("F nS Y, G:i", */$lfdu_RSLT['creationdate'];
								// COMING SOON: $lldate = stripslashes(htmlspecialchars(gm_date($lfdu_RSLT['lastlogindate'])));
								// return user info
								$from->send('{"action":"user", "xstatus":"success", "username":"'. $usrnm .'", "id":"'. $actualuid .'", "status":"'. $stts .'", "picture":"'. $pfp .'", "profilestatus":"'. $ustts .'", "mood":"'. $mood .'", "presence":"'. $pres .'", "creationDate":"'. $cdate .'"}');
								// disable unnecessary log: echo(json_encode(array("username" => $usrnm, "id" => $actualuid, "status" => $stts, "picture" => $pfp, "profilestatus" => $ustts, "creationDate" => $cdate/*, "lastLoginDate" => $lldate*/)));
							}
							// otherwise...
							else{
								// return warning telling the client that the user is unexistent
								$from->send('{"action":"user", "status":"xfail"}');
							}	
						}
					}
				}
			}
		}
	}
			break;
			case "user:by_screen_name":
				if(!empty($dataset['authentication']) and !empty($dataset['username'])){
		// isolate authentication
		$auth = stripslashes(htmlspecialchars($dataset['authentication']));

		// lfdu = look for da user
		$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
			
		// if the result is NOT a boolean (in other words an error)...
		if(!is_bool($security_lfdu)){
			// if the authentication matches a user...
			if(mysqli_num_rows($security_lfdu) != 0){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	

				// lfdu = look for da user
				$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
					
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($security_lfdu)){
					// if the authentication matches a user...
					if(mysqli_num_rows($security_lfdu) != 0){
						// isolate the user ID
						$uid = stripslashes(htmlspecialchars($dataset['username']));
		
						// lfdu = look for da user
						$lfdu = mysqli_query($ctds, "SELECT `username`, `picture`, `id`, `status`, `profilestatus`, `creationdate`, `user_public_mood`, `presence` FROM `accounts` WHERE `username`='". $uid ."'");
						
						// if there is no error...
						if(!is_bool($lfdu)){
							// if a user matching the ID exists...
							if(mysqli_num_rows($lfdu) != 0){
								// check if the user is ACTUALLY online in the first place
								$pres = "cloaked";
								foreach($this->clients as $client) {
									if($from==$client) {
										$utoken = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
										$lfduSEND = mysqli_query($ctds, "SELECT `username`, `id`, `presence` FROM `accounts` WHERE `authentication`='". $utoken ."'");
										$lfduS_RSLT = mysqli_fetch_assoc($lfduSEND);
										$upresence = stripslashes(htmlspecialchars($lfduS_RSLT['presence']));
										$pres = $upresence;
									}
								}
								// cache db result
								$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
								// isolate all of these variables JUST IN CASE
								$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
								$mood = stripslashes(htmlspecialchars($lfdu_RSLT['user_public_mood']));
								$actualuid = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
								$stts = stripslashes(htmlspecialchars($lfdu_RSLT['status']));
								$pfp = stripslashes(htmlspecialchars($lfdu_RSLT['picture']));
								$ustts = "". stripslashes(htmlspecialchars($lfdu_RSLT['profilestatus']));
								$cdate = /*stripslashes(htmlspecialchars(gmdate("F nS Y, G:i", */$lfdu_RSLT['creationdate'];
								if(empty($lfdu_RSLT['roles'])){
									$userroles = "[]";
								}
								else{
									$userroles = $lfdu_RSLT['roles'];
								}
								// COMING SOON: $lldate = stripslashes(htmlspecialchars(gm_date($lfdu_RSLT['lastlogindate'])));
								// return user info
								$from->send('{"action":"user", "xstatus":"success", "username":"'. $usrnm .'", "id":"'. $actualuid .'", "status":"'. $stts .'", "picture":"'. $pfp .'", "profilestatus":"'. $ustts .'", "mood":"'. $mood .'", "presence":"'. $pres .'", "creationDate":"'. $cdate .'", "roles":"'. $userroles .'"}');
								// disable unnecessary log: echo(json_encode(array("username" => $usrnm, "id" => $actualuid, "status" => $stts, "picture" => $pfp, "profilestatus" => $ustts, "creationDate" => $cdate/*, "lastLoginDate" => $lldate*/)));
							}
							// otherwise...
							else{
								// return warning telling the client that the user is unexistent
								$from->send('{"action":"user", "status":"xfail"}');
							}	
						}
					}
				}
			}
		}
	}
			break;
			case "account":
				if(!empty($dataset['authentication'])){
		// isolate authentication
		$auth = stripslashes(htmlspecialchars($dataset['authentication']));

		// lfdu = look for da user
		$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
			
		// if the result is NOT a boolean (in other words an error)...
		if(!is_bool($security_lfdu)){
			// if the authentication matches a user...
			if(mysqli_num_rows($security_lfdu) != 0){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	

				// lfdu = look for da user
				$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
					
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($security_lfdu)){
					// if the authentication matches a user...
					if(mysqli_num_rows($security_lfdu) != 0){
						// isolate the user ID
						$uid = stripslashes(htmlspecialchars($dataset['authentication']));
		
						// lfdu = look for da user
						$lfdu = mysqli_query($ctds, "SELECT `username`, `picture`, `id`, `status`, `profilestatus`, `creationdate`, `user_public_mood`, `presence` FROM `accounts` WHERE `authentication`='". $uid ."'");
						
						// if there is no error...
						if(!is_bool($lfdu)){
							// if a user matching the ID exists...
							if(mysqli_num_rows($lfdu) != 0){
								$pres = "cloaked";
								foreach($this->clients as $client) {
									if($from==$client) {
										$utoken = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
										$lfduSEND = mysqli_query($ctds, "SELECT `username`, `id`, `presence` FROM `accounts` WHERE `authentication`='". $utoken ."'");
										$lfduS_RSLT = mysqli_fetch_assoc($lfduSEND);
										$upresence = stripslashes(htmlspecialchars($lfduS_RSLT['presence']));
										$pres = $upresence;
									}
								}
								// cache db result
								$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
								// isolate all of these variables JUST IN CASE
								$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
								$actualuid = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
								$mood = stripslashes(htmlspecialchars($lfdu_RSLT['user_public_mood']));
								$stts = stripslashes(htmlspecialchars($lfdu_RSLT['status']));
								$pfp = stripslashes(htmlspecialchars($lfdu_RSLT['picture']));
								$ustts = "". stripslashes(htmlspecialchars($lfdu_RSLT['profilestatus']));
								$cdate = /*stripslashes(htmlspecialchars(gmdate("F nS Y, G:i", */$lfdu_RSLT['creationdate'];
								if(empty($lfdu_RSLT['roles'])){
									$userroles = "[]";
								}
								else{
									$userroles = $lfdu_RSLT['roles'];
								}
								// COMING SOON: $lldate = stripslashes(htmlspecialchars(gm_date($lfdu_RSLT['lastlogindate'])));
								// return user info
								$from->send('{"action":"account", "xstatus":"success", "username":"'. $usrnm .'", "id":"'. $actualuid .'", "status":"'. $stts .'", "picture":"'. $pfp .'", "profilestatus":"'. $ustts .'",  "creationDate":"'. $cdate .'", "presence":"'. $pres .'", "mood":"'. $mood .'", "roles":'. $userroles .'}');
								// disable unnecessary log: echo(json_encode(array("username" => $usrnm, "id" => $actualuid, "status" => $stts, "picture" => $pfp, "profilestatus" => $ustts, "creationDate" => $cdate, /*, "lastLoginDate" => $lldate*/)));
							}
							// otherwise...
							else{
								// return warning telling the client that the user is unexistent
								$from->send('{"action":"user", "status":"xfail"}');
							}	
						}
					}
				}
			}
		}
	}
			break;
			case "onlineusers":
				//extraStuff/message_intent();
				// if theres a message and/or an attachment...
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				//var_dump($this->clients);
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status`, `presence` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
					
					if(mysqli_num_rows($lfdu) != 0){
						foreach($this->clients as $client) {
							$s_auth = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
							$lfdu2 = mysqli_query($ctds, "SELECT `username`, `picture`, `profilestatus`, `id`, `roles`, `status`, `presence` FROM `accounts` WHERE `authentication`='". $s_auth ."'");
							$lfdu2_RSLT = mysqli_fetch_assoc($lfdu2);
							if(mysqli_num_rows($lfdu2) != 0){
								$from->send('{"action":"onlineuser","status":"success", "username":"'. stripslashes(htmlspecialchars($lfdu2_RSLT['username'])) .'", "id":"'. stripslashes(htmlspecialchars($lfdu2_RSLT['id'])) .'","profilestatus":"'. stripslashes(htmlspecialchars($lfdu2_RSLT['profilestatus'])) .'","presence":"'. stripslashes(htmlspecialchars($lfdu2_RSLT['presence'])) .'","picture":"'. stripslashes(htmlspecialchars($lfdu2_RSLT['picture'])) .'"}');
							}
			  			}					
					}
				}
				else{
					echo("");
				}
			}
			break;
			case "whisper":
				//extraStuff/message_intent();
				// if theres a message and/or an attachment...
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication']) and !empty($dataset['recipient']) and isset($dataset['attachment']) and isset($dataset['message'])){
				//var_dump($this->clients);
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));
				$user = stripslashes(htmlspecialchars($dataset['recipient']));
				$msg = stripslashes(htmlspecialchars($dataset['message']));
				$attachment = stripslashes(htmlspecialchars($dataset['attachment']));
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
					$repeat = 0;
					if(mysqli_num_rows($lfdu) != 0){
						foreach($this->clients as $client) {
							$s_auth = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
							$lfdu2 = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $s_auth ."'");
							$lfdu2_RSLT = mysqli_fetch_assoc($lfdu2);
							$uid = stripslashes(htmlspecialchars($lfdu2_RSLT['id']));
							$usrnm = stripslashes(htmlspecialchars($lfdu2_RSLT['username']));
							if(mysqli_num_rows($lfdu2) != 0){
								if($lfdu2_RSLT['id'] == $user)
								{
									$from->send('{"action":"whisper", "status":"success", "user":"'. stripslashes(htmlspecialchars($lfdu_RSLT['username'])) .'", "recipient":"'. $usrnm .'", "uid":"'. $uid .'", "msg":"' . $msg . '", "attachment1": "'. $attachment .'"}');
									$client->send('{"action":"whisper", "status":"success", "user":"'. stripslashes(htmlspecialchars($lfdu_RSLT['username'])) .'", "recipient":"'. $usrnm .'", "uid":"'. $uid .'", "msg":"' . $msg . '", "attachment1": "'. $attachment .'"}');
								}
							}
			  			}					
					}
				}
				else{
					echo("");
				}
			}
			break;
			case "vchannel":
				//extraStuff/message_intent();
				// if theres a message and/or an attachment...
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
						// cache db results
						$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
						$lfdc = mysqli_query($ctds, "SELECT * FROM `vchannels`");
						$lfdc_RSLT = mysqli_fetch_assoc($lfdc);
						$channel_allowed = json_decode($lfdc_RSLT['allowed_roles']);
						$userroles = json_decode($lfdu_RSLT['roles']);
						$greenlight = false;
						// isolate username, user ID
						$nm = stripslashes(htmlspecialchars($lfdc_RSLT['name']));
						$id = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
						$emkeyscount = 0;
						$mid = mt_rand(10000001, 99999999);
						/* DEPRECATED: server-side emote processing, will be removed in a future release
						foreach($emotelist as $emotelist2){
							$emotekeys = array_keys($emotelist);
							if($emkeyscount == 0){
								$actual_mesg = str_replace($emotekeys[$emkeyscount], addslashes('<img src="'. stripslashes(htmlspecialchars($emotelist2)) .'" width="32" height="32">'), $mesg);
							}
							else{
								$actual_mesg = str_replace($emotekeys[$emkeyscount], addslashes('<img src="'. stripslashes(htmlspecialchars($emotelist2)) .'" width="32" height="32">'), $actual_mesg);
							}
							$emkeyscount++;
						}*/
							// COMING SOON, will be correctly implemented in a future release
							$greenlight = true;
							if($greenlight == true){
								// if the result is successful...
								// $from->send('{"action":"message","status":"success", "user":"'. $usrnm .'", "channel":"'. $chnl .'", "uid":"'. $id .'", "msg":"' .  $actual_mesg . '","time":"'. time() .'","msgid":"'. $mid .'","attachment1":"'. $attach1 .'"}');
								if($lfdu_RSLT['status'] != "STAGING"){
									if($serverconfig['save_messages'] == true){
										// do nothing
									}
								}
					
								// if there is no error...
								if(!is_bool($lfdc)){
									$from->send('{"action":"vchannel","status":"success", "name":"'. stripslashes(htmlspecialchars($lfdc_RSLT['name'])) .'", "id":"'. stripslashes(htmlspecialchars($lfdc_RSLT['id'])) .'"}');
									$rewind = 1;				
									while($row = mysqli_fetch_assoc($lfdc)) {
										$actualid = stripslashes(htmlspecialchars($row['id']));
										$name = stripslashes(htmlspecialchars($row['name']));
										$from->send('{"action":"vchannel","status":"success", "name":"'. $name .'", "id":"'. $actualid .'"}');
										$rewind++;
			  						}						
								}
								/*foreach($lfdc_RSLT as $channel) {
									$from->send('{"action":"channel","status":"success", "name":"'. stripslashes(htmlspecialchars($channel['name'])) .'", "id":"'. stripslashes(htmlspecialchars($channel['id'])) .'"}');
								}*/
							}
							else{
								echo("");
							}
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
		
			break;
			case "edit": 
			if(!empty($dataset['message']) and !empty($dataset['msgid'])){
			// isolate information
			$mesg = stripslashes(htmlspecialchars($dataset['message']));
			$chnl = stripslashes(htmlspecialchars($dataset['msgid']));
			//$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
						// cache db results
						$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
						$lfdc = mysqli_query($ctds, "SELECT * FROM `channels` WHERE `id`='". $chnl ."'");
						$lfdc_RSLT = mysqli_fetch_assoc($lfdc);
						$channel_allowed = json_decode($lfdc_RSLT['allowed_roles']);
						$userroles = json_decode($lfdu_RSLT['roles']);
						$greenlight = false;
						// isolate username, user ID
						$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
						$id = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
						$emkeyscount = 0;
						$actual_mesg = $mesg;

						$lfdm = mysqli_query($ctds, "SELECT `author` FROM `messages` WHERE `number`='". $chnl ."'");
						$lfdm_RSLT = mysqli_fetch_assoc($lfdm);
							// COMING SOON, will be correctly implemented in a future release
							/*if(!empty($channel_allowed[0]) and $lfdm_RSLT['author'] != $id){
								for($i = 0; $i >= $userroles; $i++){
									if($userroles[$i] == $channel_allowed){
										$greenlight = true;
									}
									else{
										$greenlight = false;
									}
								}
							}
							else{
								$greenlight = true;
							}*/
							if($lfdm_RSLT['author'] != $id){
								$greenlight = false;
							}
							else{
								$greenlight = true;
							}

							if($greenlight == true){
								// if the result is successful...
								$from->send('{"action":"edit", "status":"success", "user":"'. $usrnm .'", "msgid":"'. $chnl .'", "uid":"'. $id .'", "msg":"' .  $actual_mesg . '","time":"'. time() .'", "attachment1":"'. $attach1 .'"}');
								if($lfdu_RSLT['status'] != "STAGING"){
									if($serverconfig['save_messages'] == true){
										// insert into 'messages' table
										echo("[Satellite] Message edit saved\n");
										$query = "UPDATE `messages` SET `content`='". $actual_mesg ."' WHERE `number`='". $chnl ."'";    
		        							$submit = mysqli_query($ctds, $query);
									}
								}
								foreach($this->clients as $client) {
									if($from!=$client) {
										$utoken = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
										$lfduSEND = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $utoken ."'");
										$lfduS_RSLT = mysqli_fetch_assoc($lfduSEND);
										$userrolesS = json_decode($lfduS_RSLT['roles']);
										/* TODO: Implement later
										for($i = 0; $i >= $userrolesS; $i++){
											echo($userrolesS . " " . $channel_allowed . "\n");
											if($userrolesS[$i] == $channel_allowed){
												$greenlight = true;
											}
											else{
												$greenlight = false;
											}
										}*/
										$greenlight = true;
										if($greenlight == true){
											$client->send('{"action":"edit", "status":"success", "user":"'. $usrnm .'", "msgid":"'. $chnl .'", "uid":"'. $id .'", "msg":"' .  $actual_mesg . '","time":"'. time() .'", "attachment1":"'. $attach1 .'"}');
											echo("[Satellite] Message by ". stripslashes(htmlspecialchars($usrnm)) ." successfully edited!\n");
										}
										else{
											// do nothing. lol
											echo("");
										}
									}
								}
							}
							else{
								echo("");
							}
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}
			break;
			case "administrative:ban_by_screen_name": 
			if(!empty($dataset['username'])){
			// isolate information
			$user = stripslashes(htmlspecialchars($dataset['username']));
			//$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
						// cache db results
						$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
						if(empty($lfdu_RSLT['roles'])){
							$userroles = "[]";
						}
						else{
							$userroles = json_decode($lfdu_RSLT['roles']);
							echo(var_dump($userroles));
						}
						$greenlight = false;
						// isolate username, user ID
						$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
						$id = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
						$emkeyscount = 0;
						$actual_mesg = $mesg;

						$lfdutb = mysqli_query($ctds, "SELECT `username`, `id`, `status`, `roles` FROM `accounts` WHERE `username`='". $user ."'");
						$lfdutb_RSLT = mysqli_fetch_assoc($lfdutb);
						if(!empty($userroles)){
							if($userroles[0]){
								$greenlight = true;
								$query = "UPDATE `accounts` SET `status`='BANNED' WHERE `id`='". stripslashes(htmlspecialchars($lfdutb_RSLT['id'])) ."'";
								$banSEND = mysqli_query($ctds, $query);
							
							/*for($i = 0; $i >= $userroles; $i++){
								if($userroles[$i] == 'admin'){
									
								}
								else{
									$greenlight = false;
								}
							}*/

							if($greenlight == true){
								// if the result is successful...
								$from->send('{"action":"administrative:ban_alert", "status":"success", "user":"'. $user .'", "uid":"'. stripslashes(htmlspecialchars($lfdutb_RSLT['id'])) .'", "legacy_msg":"' .  $user . ' has been BANNED!"}');
								foreach($this->clients as $client) {
									if($from!=$client) {
										$utoken = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
										$lfduSEND = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $utoken ."'");
										$lfduS_RSLT = mysqli_fetch_assoc($lfduSEND);
										$selected_username = stripslashes(htmlspecialchars($lfduS_RSLT['username']));
										$greenlight = true;
										if($user == $selected_username){
											$client->send('{"status":"fail", "error":"or_you_will_get_clapped"}');
											$this->clients->detach($client);
											$client->close();
											echo("[Satellite] User account ". stripslashes(htmlspecialchars($user)) ." successfully BANNED!\n");
										}
										else{
											// do nothing. lol
											echo("");
										}
									}
								}
							}
							else{
								echo("");
							}}}
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}
			break;
			case "delete": 
			if(!empty($dataset['msgid'])){
			// isolate information
			$chnl = stripslashes(htmlspecialchars($dataset['msgid']));
			//$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
						// cache db results
						$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
						$lfdc = mysqli_query($ctds, "SELECT * FROM `channels` WHERE `id`='". $chnl ."'");
						$lfdc_RSLT = mysqli_fetch_assoc($lfdc);
						$channel_allowed = json_decode($lfdc_RSLT['allowed_roles']);
						$userroles = json_decode($lfdu_RSLT['roles']);
						$greenlight = false;
						// isolate username, user ID
						$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
						$id = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
						$emkeyscount = 0;
						$actual_mesg = $mesg;

						$lfdm = mysqli_query($ctds, "SELECT `author` FROM `messages` WHERE `number`='". $chnl ."'");
						$lfdm_RSLT = mysqli_fetch_assoc($lfdm);
							// COMING SOON, will be correctly implemented in a future release
							/*if(!empty($channel_allowed[0]) and $lfdm_RSLT['author'] != $id){
								for($i = 0; $i >= $userroles; $i++){
									if($userroles[$i] == $channel_allowed){
										$greenlight = true;
									}
									else{
										$greenlight = false;
									}
								}
							}
							else{
								$greenlight = true;
							}*/
							if($lfdm_RSLT['author'] != $id){
								$greenlight = false;
							}
							else{
								$greenlight = true;
							}

							if($greenlight == true){
								// if the result is successful...
								$from->send('{"action":"delete", "status":"success", "msgid":"'. $chnl .'","time":"'. time() .'"}');
								if($lfdu_RSLT['status'] != "STAGING"){
									if($serverconfig['save_messages'] == true){
										// insert into 'messages' table
										echo("[Satellite] Message deletion saved\n");
										$query = "DELETE FROM `messages` WHERE `number`='". $chnl ."'";    
		        							$submit = mysqli_query($ctds, $query);
									}
								}
								foreach($this->clients as $client) {
									if($from!=$client) {
										$utoken = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
										$lfduSEND = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $utoken ."'");
										$lfduS_RSLT = mysqli_fetch_assoc($lfduSEND);
										$userrolesS = json_decode($lfduS_RSLT['roles']);
										for($i = 0; $i >= $userrolesS; $i++){
											echo($userrolesS . " " . $channel_allowed . "\n");
											if($userrolesS[$i] == $channel_allowed){
												$greenlight = true;
											}
											else{
												$greenlight = false;
											}
										}
										if($greenlight == true){
											$client->send('{"action":"delete", "status":"success", "msgid":"'. $chnl .'","time":"'. time() .'"}');
											echo("[Satellite] Message by ". stripslashes(htmlspecialchars($usrnm)) ." successfully deleted!\n");
										}
										else{
											// do nothing. lol
											echo("");
										}
									}
								}
							}
							else{
								echo("");
							}
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}
			break;
			case "editprofile:picture": 
			if(!empty($dataset['pfp'])){
			// isolate information
			$pfp = stripslashes(htmlspecialchars($dataset['pfp']));
			//$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
							// if the result is successful...
							$from->send('{"action":"editprofile", "status":"success"}');
							$lfdpfp = mysqli_query($ctds, "UPDATE `accounts` SET `picture`='". $pfp ."' WHERE `authentication`='". $auth ."'");
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}
			break;
			case "editprofile:username": 
			if(!empty($dataset['username'])){
			// isolate information
			$username = stripslashes(htmlspecialchars($dataset['username']));
			//$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
							// if the result is successful...
							$dupelfdu = mysqli_query($ctds, 'SELECT `username` FROM `accounts`');
							$strings = mysqli_fetch_assoc($dupelfdu);
							$ok = true;
							while($accounts = mysqli_fetch_assoc($dupelfdu)) {
								if($username == $accounts['username']){
									echo("[Satellite] Username already taken! Aborting.\n");
									$ok = false;
									$from->send('{"action":"editprofile", "status":"fail", "error":"Username already taken!"}');
								}
								elseif($username == "System"){
									echo("[Satellite] Username is 'System'! Aborting.\n");
									$ok = false;
									$from->send('{"action":"editprofile", "status":"fail", "error":"A man can only dream."}');
								}
  							}
							if($ok == true){
								$from->send('{"action":"editprofile", "status":"success"}');
								$lfdu = mysqli_query($ctds, "UPDATE `accounts` SET `username`='". $username ."' WHERE `authentication`='". $auth ."'");
							}
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}
			break;
			case "editprofile:status": 
			if(!empty($dataset['status'])){
			// isolate information
			$status = stripslashes(htmlspecialchars($dataset['status']));
			//$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
							// if the result is successful...
							$from->send('{"action":"editprofile", "status":"success"}');
							$lfdpfp = mysqli_query($ctds, "UPDATE `accounts` SET `profilestatus`='". $status ."' WHERE `authentication`='". $auth ."'");
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}
			break;
			case "editprofile:mood": 
			if(!empty($dataset['mood'])){
			// isolate information
			$status = stripslashes(htmlspecialchars($dataset['mood']));
			//$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
							// if the result is successful...
							$from->send('{"action":"editprofile", "status":"success"}');
							$lfdpfp = mysqli_query($ctds, "UPDATE `accounts` SET `user_public_mood`='". $status ."' WHERE `authentication`='". $auth ."'");
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}
			break;
			case "editprofile:presence": 
			if(!empty($dataset['presence'])){
			if($dataset['presence'] == "online" or $dataset['presence'] == "idle" or $dataset['presence'] == "dnd" or $dataset['presence'] == "cloaked"){
			// isolate information
			$status = stripslashes(htmlspecialchars($dataset['presence']));
			//$attach1 = stripslashes($dataset['attachment1']);
			// goofy system, will rework later on
			$output = '{"messages":';
			// if authentication is set...
			if(!empty($dataset['authentication'])){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($dataset['authentication']));	
	
				// lfdu = look for da user
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
						if(mysqli_num_rows($lfdu) != 0){
							// if the result is successful...
							$from->send('{"action":"editprofile", "status":"success"}');
							$lfdpfp = mysqli_query($ctds, "UPDATE `accounts` SET `presence`='". $status ."' WHERE `authentication`='". $auth ."'");
						}
					}
					else{
						echo('{"status":"authfail"}');
					}	
				}
			}}
			break;
			case "editprofile:password": 
			if(!empty($dataset['authentication']) and !empty($dataset['old_password']) and !empty($dataset['password'])){
				// isolate them
				$username = stripslashes(htmlspecialchars($dataset['authentication']));
				$password = $dataset['old_password'];
				// time to check
				$lfdu = mysqli_query($ctds, 'SELECT * FROM `accounts` WHERE `authentication`="'. $username .'"');
				// cache db results
				$strings = mysqli_fetch_assoc($lfdu);
				// ???
				$attempts = 1;
				// does the password given by the user match the hash?
				if(mysqli_num_rows($lfdu) > 0 and password_verify($old_password, $strings['password'])){
					// yes? good. swap the password with the new one and provide the new token
					$lfdpfp = mysqli_query($ctds, "UPDATE `accounts` SET `password`='". password_hash($password) ."' WHERE `authentication`='". $auth ."'");
				}
				// no? tell them to back off
				else{
					$from->send('{"action":"editprofile:password", "status":"fail", "error":"incorrect_credentials"}');
				}
			}
			break;
			default:
				// uhm excuse me what the fuck
				echo("[Satellite] User request could not be determined\n");
			break;
		}
	}
	public function onError(ConnectionInterface $conn, \Exception $e) {
		$conn->close();
		echo("ERROR! ". $e ."\n");
	}
}

/* use this code instead if u DON'T want an encrypted connection:

$server = new Ratchet\App('?', 7778, '0.0.0.0');
$server->route('/', new Chatroom, ['*']);

*/
$configpath = file_get_contents("./SatelliteConfig.json");
$config = json_decode($configpath, true);
$loop = React\EventLoop\Factory::create();
$webSock = new React\Socket\Server('0.0.0.0:' . $config['port'], $loop);
$webSock = new React\Socket\SecureServer($webSock, $loop, [
    'local_cert'        => $config['cert_path'],
    'local_pk'          => $config['pkey_path'],
    'allow_self_signed' => !$config['force_secure'],
    'verify_peer' => FALSE
]);

$webServer = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer(
            new Chatroom()
        )
    ),
    $webSock,
    $loop
);

$webServer->run();
?>
