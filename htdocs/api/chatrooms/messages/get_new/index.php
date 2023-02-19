<?php

	// connect to the database and get server settings
	include("../../connect.php");
	// NEW SECURITY CONCEPT: if authentication is set, let the user know
	if(!empty($_POST['authentication'])){
		// isolate authentication
		$auth = stripslashes(htmlspecialchars($_POST['authentication']));

		// lfdu = look for da user
		$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
		$slfdu_rslts = mysqli_fetch_assoc($security_lfdu);
			
		// if the result is NOT a boolean (in other words an error)...
		if(!is_bool($security_lfdu)){
			// if the authentication matches a user...
			if(mysqli_num_rows($security_lfdu) != 0 and $slfdu_rslts['status'] == "ok" or mysqli_num_rows($security_lfdu) and $slfdu_rslts['status'] == "OK"){
				// is a channel set?
				if(!empty($_POST['channel'])){
					// lfdc = look for da channel
					// TODO: ORDER BY `messages`.`date` DESC 
					$lfdc = mysqli_query($ctds, "SELECT * FROM `messages` WHERE `channel`='". $_POST['channel'] ."' ORDER BY `messages`.`date` DESC LIMIT 256");
				}
				else{
					die();
				}
				
				// if there is no error...
				if(!is_bool($lfdc)){
					// if there are messages...
					if(mysqli_num_rows($lfdc) != 0){
						// cache channel result
						$lfdc_RSLT = mysqli_fetch_assoc($lfdc);
						// if the account exists, use the username
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
						// is there a first message? yes? good. add it to the list
						$returnjson = array(array("id" => $lfdc_RSLT['number'], "author" => $lfdc_RSLT['author'], "channel" => $lfdc_RSLT['channel'], "content" => stripslashes(htmlspecialchars($lfdc_RSLT['content'])), "date" => $lfdc_RSLT['date'], "username" => $first_authoruname, "attachment1" => $lfdc_RSLT['attachment1']));		
						$rewind = 256;
						// repeat this for every other message		
						while($row = mysqli_fetch_assoc($lfdc)) {
							// isolate properties
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
							$rewind--;
			  			}
						// return the results to the client				
						echo(json_encode($returnjson));
					}
					// otherwise...
					else{
						// return warning telling the client that there are no messages
						returnWarnResponse("No messages available!");
					}	
				}
			}
		}
	}
	// otherwise just kick them out lol
	else{
		returnWarnResponse("Please provide authentication");
	}
?>
