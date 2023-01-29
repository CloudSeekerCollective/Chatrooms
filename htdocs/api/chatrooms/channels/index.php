<?php

	// connect to the database and get server settings
	include("../connect.php");
	// NEW SECURITY CONCEPT: if authentication is set, let the user know
	if(!empty($_POST['authentication'])){
		// isolate authentication
		$auth = stripslashes(htmlspecialchars($_POST['authentication']));

		// lfdu = look for da user
		$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `authentication`='". $auth ."'");
			
		// if the result is NOT a boolean (in other words an error)...
		if(!is_bool($security_lfdu)){
			// if the authentication matches a user...
			if(mysqli_num_rows($security_lfdu) != 0){
				// if the result is NOT a boolean (in other words an error)...
				if(!is_bool($security_lfdu)){
					// lfdc = look for da channel
					$lfdc = mysqli_query($ctds, "SELECT * FROM `channels`");
					
					// if there is no error...
					if(!is_bool($lfdc)){
						$lfdc_RSLT = mysqli_fetch_assoc($lfdc);
						$returnjson = array(array("id" => $lfdc_RSLT['id'], "name" => $lfdc_RSLT['name']));
						$rewind = 1;				
						while($row = mysqli_fetch_assoc($lfdc)) {
							$actualid = stripslashes(htmlspecialchars($row['id']));
							$name = stripslashes(htmlspecialchars($row['name']));
							$returnjson[$rewind] = array(
								"id" => $actualid, 
								"name" => $name
						);
							$rewind++;
			  			}						
						echo(json_encode($returnjson));
					}
					// otherwise...
					else{
						// return warning telling the client that there are no available channels
						returnWarnResponse("No channels are available!");
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
