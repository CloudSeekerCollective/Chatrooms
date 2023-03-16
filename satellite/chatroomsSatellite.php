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
		echo("Welcome to the Chatrooms Experience! (Compiled on 08/02/2023)\n
      Chatrooms is a free, open source and lightweight chat platform where anyone can host a space for their friends, people and even family to hang out.
    Copyright (C) 20". date("y") ."  Team CloudSeeker (Popular Toppling Jelly/Teodor Boshkoski and GeofTheCake)\n

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
		sleep(5);
		$configpath = file_get_contents("./SatelliteConfig.json");
		$config = json_decode($configpath, true);
		$ctds = mysqli_connect("localhost", $config['mysql_username'], $config['mysql_password'], "chrms_universe");
		$reset_online_users = mysqli_query($ctds, "UPDATE `accounts` SET `is_online`='0' WHERE 1");
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
			if($conn->httpRequest->getUri()->getQuery() == "ping"){
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
		//use function extraStuff/message_intent;
		//use function extraStuff/edit_intent;
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
		// DEPRECATED: server-side emote processing, will be removed in future update
		$emotelist = array(
			":usus:" => "./assets/emotes/usus.png",
			":angy:" => "./assets/emotes/angy.png",
			":happ:" => "./assets/emotes/happ.png",
			":sadus:" => "./assets/emotes/sadus.png",
			":wtf:" => "./assets/emotes/wtf.png",
			":flushy:" => "./assets/emotes/flushy.png",
		);
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
				$lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
				
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($lfdu)){
					// if the authentication matches a user...
					
					if(mysqli_num_rows($lfdu) != 0){
						foreach($this->clients as $client) {
							$s_auth = substr(stripslashes(htmlspecialchars($client->httpRequest->getUri()->getQuery())), 5);
							$lfdu2 = mysqli_query($ctds, "SELECT `username`, `id`, `roles`, `status` FROM `accounts` WHERE `authentication`='". $s_auth ."'");
							$lfdu2_RSLT = mysqli_fetch_assoc($lfdu2);
							if(mysqli_num_rows($lfdu2) != 0){
								$from->send('{"action":"onlineuser","status":"success", "username":"'. stripslashes(htmlspecialchars($lfdu2_RSLT['username'])) .'", "id":"'. stripslashes(htmlspecialchars($lfdu2_RSLT['id'])) .'"}');
							}
							$rewind++;
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
										// insert into 'messages' table
										echo("[Satellite] ???\n");
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
			case "delete": 
			if(!empty($dataset['message']) and !empty($dataset['msgid'])){
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
								}
								elseif($username == "System"){
									echo("[Satellite] Username is 'System'! Aborting.\n");
									$ok = false;
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
    'allow_self_signed' => TRUE,
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
