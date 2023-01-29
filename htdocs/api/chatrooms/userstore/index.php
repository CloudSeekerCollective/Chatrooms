<?php
	include("../connect.php");

	// NEW SECURITY CONCEPT: if authentication is set, let the user know
	/*if(!empty($_POST['authentication'])){
		// isolate authentication
		$auth = stripslashes(htmlspecialchars($_POST['authentication']));

		// lfdu = look for da user
		$security_lfdu = mysqli_query($ctds, "SELECT `username`, `id`, `status` FROM `accounts` WHERE `authentication`='". $auth ."'");
		$slfdu_rslts = mysqli_fetch_assoc($security_lfdu);
			
		// if the result is NOT a boolean (in other words an error)...
		if(!is_bool($security_lfdu)){
			// if the authentication matches a user...
			if(mysqli_num_rows($security_lfdu) != 0 and $slfdu_rslts['status'] == "ok" or mysqli_num_rows($security_lfdu) and $slfdu_rslts['status'] == "OK"){*/
				$target_dir = "../../../userstore/";
				$unhashed_target_file = $target_dir . basename($_FILES["CHATROOMS_UPLOAD"]["name"]);
				$filetype = strtolower(pathinfo($unhashed_target_file, PATHINFO_EXTENSION));
				$target_file = $target_dir . md5(basename($_FILES["CHATROOMS_UPLOAD"]["name"]) . time()) . "." . $filetype;
				$target_file_nodir = md5(basename($_FILES["CHATROOMS_UPLOAD"]["name"]) . time()) . "." . $filetype;
				$death_reason = "";
				$uploadOk = 1;

				// check the file
				if(isset($_POST["submit"])) {
			  		$check = getimagesize($_FILES["CHATROOMS_UPLOAD"]["tmp_name"]);
			  		if($check !== false){
    						$uploadOk = 1;
  					}
					else{
    						$death_reason = "FILE_TYPE_DISALLOWED_BYPASS";
    						$uploadOk = 0;
  					}
				}

				// if the file already exists, CREEEYZI HAMBURGER!!! IT IS HORIBEL!!!
				if (file_exists($target_file)) {
	 			 	$death_reason = "CRAZY_HAMBURGER";
	  				$uploadOk = 0;
				}
	
				// if the file is over specified size limit, stop
				if ($_FILES["CHATROOMS_UPLOAD"]["size"] > $serverconfig['filesize_limit']) {
	  				$death_reason = "FILE_OVER_LIMITS";
	  				$uploadOk = 0;
				}
	
				/*if($filetype != "jpg" 
				and $filetype != "png" 
				and $filetype != "jpeg" 
				and $filetype != "gif" 
				and $filetype != "webp" 
				and $filetype != "mp4" 
				and $filetype != "mkv" 
				and $filetype != "webm" 
				and $filetype != "mp3" 
				and $filetype != "ogg") {
	  				$death_reason = "FILE_TYPE_DISALLOWED";
			  		$uploadOk = 0;
				}*/
	
				// can i upload the file?
				if ($uploadOk == 0) {
					// no? L
	  				echo('{"error":"'. $death_reason .'", "status":"failure"}');
				} 
				else{
					// yes? move the file
					if(move_uploaded_file($_FILES["CHATROOMS_UPLOAD"]["tmp_name"], $target_file)){
		   				// did it succeed? if yes, then yippee
		    				echo('{"file_path":"/userstore/'. $target_file_nodir .'", "status":"success", "filetype":"'. $filetype .'"}');
					} 
					else{
		    				// if not, then what the fuck
		    				echo('{"error":"INTERNAL_FILESYSTEM_ERROR", "status":"failure"}');
	  				}
				}
			/*}
		}
	}*/
?>

