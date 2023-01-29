<?php 

	// get database information and server settings
	include("../../../connectsfs.php");

	// i am isolating everything that the user could possibly send to the server, just in case, god forbid a vulnerability is found
	// btw, checks if request type is POST
	if(stripslashes(htmlspecialchars($_SERVER['REQUEST_METHOD'])) == "POST"){
		// if authentication is set...
		if(!empty($_POST['authentication']) and !empty(stripslashes(htmlspecialchars($_POST['time'])))){
			// isolate authentication
			$auth = stripslashes(htmlspecialchars($_POST['auth_token']));
			$authtype = stripslashes(htmlspecialchars($_POST['auth_type']));	

			// lfdu = look for da user
			$lfdu = mysqli_query($ctds, "SELECT * FROM `accounts` WHERE `authentication`='". $auth ."'");
			
			// if the result is NOT a boolean (in other words an error)...
			if(!is_bool($lfdu)){
				// if the authentication matches a user...
				if(mysqli_num_rows($lfdu) != 0){
					// cache db result
					$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
					// isolate username, user ID, etc
					$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
					$id = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
					$chnl = stripslashes(htmlspecialchars($lfdu_RSLT['channel']));
					// select from 'messages' table
					$query = "SELECT * FROM `messages` WHERE `date`='". $time ."'";    
	        			$submit = mysqli_query($ctds, $query);
					$getresult = mysqli_fetch_assoc($submit);
					// if the result is successful...
					//if(!is_bool($submit)){
					$output = '{"messages":';
						if(mysqli_num_rows($submit) > 0){
							//var_dump($getresult);
							$resultset = array();
							for($heheheha = 0; $heheheha > $getresult; $heheheha++){
								var_dump($heheheha);
								$lfduAgain = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `id`='". $heheheha['author'] ."'");
								$lfduAgain_RSLT = mysqli_fetch_assoc($lfduAgain);
								$output = '[{"status":"success", "user":"'. $lfduAgain_RSLT['username'] .'", "channel":"'. $chnl .'", "uid":"'. $heheheha['author'] .'", "msg":"' .  $heheheha['content'] . '"}]},';
							}
							/*foreach($heheheha as $resultset){
								$lfduAgain = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `id`='". $getresult['author'] ."'");
								$lfduAgain_RSLT = mysqli_fetch_assoc($lfduAgain);
								
								$output = $output .'[{"status":"success", "user":"'. $lfduAgain_RSLT['username'] .'", "uid":"'. $getresult['author'] .'", "msg":"' .  $getresult['content'] . '"}],';
								//var_dump($getresult);
							}*/
							$lfduAgain = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `id`='". $getresult['author'] ."'");
							$lfduAgain_RSLT = mysqli_fetch_assoc($lfduAgain);
							echo($output .'[{"status":"success", "user":"'. $lfduAgain_RSLT['username'] .'", "channel":"'. $chnl .'", "uid":"'. $getresult['author'] .'", "msg":"' .  $getresult['content'] . '"}]}');
						}
						else{
							echo('');
						}
					/*}
					// if not, tell the client that there was a database error
					else{echo('{"status":"dbfail"}');}*/
				}
				else{
					// TODO: goofy ahh code. looking into fixing this for anonymous users later on
					/*$query = "INSERT INTO `messages`(`author`, `content`, `channel`, `date`, `number`) VALUES ('Anonymous', '". $_POST['id'] ."',  '". $mesg ."')";    
	        			$submit = mysqli_query($ctds, $query);
					// if the result is successful...
					if(!is_bool($submit)){
						echo('{"status":"success", "id":"", "user":"Anonymous"}');
					}*/
					// if not, tell the client that there was a database error
					echo('{"status":"authfail"}');
				}	
			}
		}
	}
	// if the request type is NOT POST or the message is empty, tell the client that something went wrong
	else{
		echo('{"status":"otherfail"}');	
	}

?>
