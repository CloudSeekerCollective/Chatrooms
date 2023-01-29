<?php

	// connect to the database and get server settings
	include("../connect.php");

	// lfdu = look for da users
	$lfdu = mysqli_query($ctds, "SELECT `username`, `id` FROM `accounts` WHERE `is_online`='1'");
	
	// if there is no error...
	if(!is_bool($lfdu)){
		// if there are online users...
		if(mysqli_num_rows($lfdu) != 0){
			// json trol
			$returnjson = '{"online_users": {';
			// cache db result
			$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
			// return users
			// kinda stupid thing but i need it for the json to be parsed correctly
			if(!empty($lfdu_RSLT['username']) and mysqli_num_rows($lfdu) > 1){
				$returnjson = $returnjson .'"0": {"id": "'. $lfdu_RSLT['id'] .'","name": "'. $lfdu_RSLT['username'] .'"},';
			}
			elseif(!empty($lfdu_RSLT['username']) and mysqli_num_rows($lfdu) == 1){
				$returnjson = $returnjson .'"0": {"id": "'. $lfdu_RSLT['id'] .'","name": "'. $lfdu_RSLT['username'] .'"}';
			}
			$rewind = 0;
			$firsttime = 1;
  			while($row = mysqli_fetch_assoc($lfdu)) {
				$usernm = stripslashes(htmlspecialchars($row['username']));
				$actualuid = stripslashes(htmlspecialchars($row['id']));
				if(mysqli_num_rows($lfdu) > 1){
					if($rewind != (mysqli_num_rows($lfdu) - 2)){
						$returnjson = $returnjson .'"'. ($rewind+1) .'": {"id": "'. $actualuid .'","name": "'. $usernm .'"},';
						$rewind++;
					}
					else{
						$returnjson = $returnjson .'"'. ($rewind+1) .'": {"id": "'. $actualuid .'","name": "'. $usernm .'"}';
					}
				}
				else{
					if($rewind != (mysqli_num_rows($lfdu) - 2)){
						$returnjson = $returnjson .'"'. $rewind .'": {"id": "'. $actualuid .'","name": "'. $usernm .'"},';
						$rewind++;
					}
					else{
						$returnjson = $returnjson .'"'. $rewind .'": {"id": "'. $actualuid .'","name": "'. $usernm .'"}';
					}
				}
  			}
			echo($returnjson ."}}");
		}
		// otherwise...
		else{
			// return warning telling the client that there are no online users
			returnWarnResponse("No users are online!");
		}	
	}
?>
