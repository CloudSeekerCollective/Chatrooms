<?php 
	include("../connect.php");
	// did the client actually even send anything? yes? continue
	if(!empty($_POST['Luser']) and !empty($_POST['Lpass'])){
		// isolate username
		$username = stripslashes(htmlspecialchars($_POST['Luser']));
		$password = $_POST['Lpass'];

		// time to check
		$lfdu = mysqli_query($ctds, 'SELECT * FROM `accounts` WHERE `username`="'. $username .'"');
		// cache db results
		$strings = mysqli_fetch_assoc($lfdu);
		// ???
		$attempts = 1;
		// does the password given by the user match the hash?
		if(mysqli_num_rows($lfdu) > 0 and password_verify($password, $strings['password'])){
			// yes? good. give them the token
			echo('{"token":"' . $strings['authentication'] . '"}');
		}
		// no? tell them to back off
		else{
			echo("{'error':'incorrect_credentials'}");
		}
	}
	// no? tell them to back off
	else{
		echo("{'error':'incorrect_credentials'}");
	}
?>
