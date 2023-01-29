<?php 
	// get database information and server settings
	include("../../connect.php");
	// i am isolating everything that the user could possibly send to the server, just in case, god forbid a vulnerability is found
	// btw, checks if request type is POST
	if(stripslashes(htmlspecialchars($_SERVER['REQUEST_METHOD'])) == "POST" and !empty($_POST['message'])){
		// ESPECIALLY THE MESSAGE. this is the most common way of exploiting a system, so the message will always be the most heavily isolated one
		$mesg = /*stripslashes(htmlspecialchars(*/$_POST['message']/*))*/;
		$chnl = stripslashes(htmlspecialchars($_POST['channel']));
		// if authentication is set...
		if(!empty($_POST['authentication'])){
			// isolate authentication
			$auth = stripslashes(htmlspecialchars($_POST['authentication']));	

			// lfdu = look for da user
			$lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
			
			// if the result is NOT a boolean (in other words an error)...
			if(!is_bool($lfdu)){
				// if the authentication matches a user...
				if(mysqli_num_rows($lfdu) != 0){
					// cache db result
					$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
					// isolate username, user ID
					$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
					$id = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
					$emkeyscount = 0;
					foreach($emotelist as $emotelist2){
						$emotekeys = array_keys($emotelist);
						if($emkeyscount == 0){
							$actual_mesg = str_replace($emotekeys[$emkeyscount], "<img src=\'". stripslashes(htmlspecialchars($emotelist2)) ."\' width=\'32\' height=\'32\'>", $mesg);
						}
						else{
							$actual_mesg = str_replace($emotekeys[$emkeyscount], "<img src=\'". stripslashes(htmlspecialchars($emotelist2)) ."\' width=\'32\' height=\'32\'>", $actual_mesg);
						}
						$emkeyscount++;
					}
					// insert into 'messages' table
					$query = "INSERT INTO `messages`(`author`, `content`, `channel`, `date`, `number`) VALUES ('". $id ."',  '". $actual_mesg ."', '". $chnl ."', '". time() ."', '1')";    
	        			$submit = mysqli_query($ctds, $query);
					// if the result is successful...
					//if(!is_bool($submit)){
						echo('{"status":"success", "uid":"'. $id .'", "user":"'. $usrnm .'", "msg":"' . $mesg . '"}');
					/*}
					// if not, tell the client that there was a database error
					else{echo('{"status":"dbfail"}');}*/
				}
				else{
					echo('{"status":"authfail"}');
					/*$query = "INSERT INTO `messages`(`author`, `content`, `channel`, `date`, `number`) VALUES ('Anonymous', '". $_POST['id'] ."',  '". $mesg ."')";    
	        			$submit = mysqli_query($ctds, $query);
					// if the result is successful...
					//if(!is_bool($submit)){
						echo('{"status":"success", "id":"", "user":"Anonymous"}');
					/*}
					// if not, tell the client that there was a database error
					else{echo('{"status":"dbfail"}');}*/
				}	
			}
		}
	}
	// if the request type is NOT POST or the message is empty, tell the client that something went wrong
	else{
		echo('{"status":"otherfail"}');	
	}

?>
