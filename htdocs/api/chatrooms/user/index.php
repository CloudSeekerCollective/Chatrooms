<?php

	// connect to the database and get server settings
	include("../connect.php");
	// NEW SECURITY CONCEPT: if authentication is set, let the user know
	if(!empty($_POST['authentication']) and !empty($_POST['id'])){
		// isolate authentication
		$auth = stripslashes(htmlspecialchars($_POST['authentication']));

		// lfdu = look for da user
		$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
			
		// if the result is NOT a boolean (in other words an error)...
		if(!is_bool($security_lfdu)){
			// if the authentication matches a user...
			if(mysqli_num_rows($security_lfdu) != 0){
				// isolate authentication
				$auth = stripslashes(htmlspecialchars($_POST['authentication']));	

				// lfdu = look for da user
				$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
					
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($security_lfdu)){
					// if the authentication matches a user...
					if(mysqli_num_rows($security_lfdu) != 0){
						// isolate the user ID
						$uid = stripslashes(htmlspecialchars($_POST['id']));
		
						// lfdu = look for da user
						$lfdu = mysqli_query($ctds, "SELECT `username`, `picture`, `id`, `status`, `creationdate` FROM `accounts` WHERE `id`='". $uid ."'");
						
						// if there is no error...
						if(!is_bool($lfdu)){
							// if a user matching the ID exists...
							if(mysqli_num_rows($lfdu) != 0){
								// cache db result
								$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
								// isolate all of these variables JUST IN CASE
								$usrnm = stripslashes(htmlspecialchars($lfdu_RSLT['username']));
								$actualuid = stripslashes(htmlspecialchars($lfdu_RSLT['id']));
								$stts = stripslashes(htmlspecialchars($lfdu_RSLT['status']));
								$pfp = stripslashes(htmlspecialchars($lfdu_RSLT['picture']));
								$ussts = stripslashes(htmlspecialchars($lfdu_RSLT['profilestatus']));
								$cdate = stripslashes(htmlspecialchars(gmdate("F nS Y, G:i", $lfdu_RSLT['creationdate'])));
								// COMING SOON: $lldate = stripslashes(htmlspecialchars(gm_date($lfdu_RSLT['lastlogindate'])));
								// return user info
								echo(json_encode(array("username" => $usrnm, "id" => $actualuid, "status" => $stts, "picture" => $pfp, "profilestatus" => $ustts, "creationDate" => $cdate/*, "lastLoginDate" => $lldate*/)));
							}
							// otherwise...
							else{
								// return warning telling the client that the user is unexistent
								returnWarnResponse("User doesnt exist.");
							}	
						}
					}
				}
			}
		}
	}
	// otherwise just kick them out lol
	else{
		returnWarnResponse("Please provide authentication and/or user id");
	}

?>
