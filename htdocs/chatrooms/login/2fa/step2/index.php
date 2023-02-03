<!DOCTYPE html>
<?php 
	$ctds = mysqli_connect("localhost", "root", "rL_3UlKhna*nHTZ2", "chrms_universe");
	$scpath = file_get_contents("/opt/lampp/htdocs/serverproperties.json");
	$serverconfig = json_decode($scpath, true);
	if($serverconfig['require_email'] == false){
		header("Location: /chatrooms/login/success.php");
	} 
	//include("ip.php");

	if(empty($_COOKIE['authentication'])){
		header("Server: CloudSeeker");
		header("Location: login.php");
	}
	else{

		header("Server: CloudSeeker");
		$h = "MissingAccount";
	}

	if(!empty($_POST['Lkey']) and !empty($_POST['email_confirm'])){
		// isolate the user creds
		$utoken = stripslashes(htmlspecialchars($_POST['Lkey']));
		// lfdu = look for da user
		$lfdu = mysqli_query($ctds, "SELECT * FROM `accounts` WHERE `latest2fa`='". $_POST['Lkey'] ."'");
	
		// if there is no error...
		if(!is_bool($lfdu)){
			// if a user matching the ID exists...
			if(mysqli_num_rows($lfdu) != 0){
				// cache db result
				$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
				// isolate all of these variables JUST IN CASE
				// proceed to redirect user
				if(stripslashes(htmlspecialchars($lfdu_RSLT['latest2fa'])) == $_POST['Lkey']){
					$aceptar = mysqli_query($ctds, "UPDATE `accounts` SET `2fa_admission`='1' WHERE `authentication`='". $lfdu_RSLT['authentication'] ."'");
					header("Location: /chatrooms/login/success.php");
				}
				else{
					header("Location: /chatrooms/login/2fa/step2/?loginfail&reason=Incorrect 2fa code!");
				}
			}
			else{
				// return warning telling the client that the user is unexistent
				header("Location: /chatrooms/login/2fa/step2/?loginfail&reason=Incorrect 2fa code!");
			}
		}
		else{
			// return warning telling the client that the user is unexistent
			header("Location: /chatrooms/login/step2/?loginfail&reason=You do not have a 2FA code; one has been given to you. Check your inbox!");
		}
	}
	
	if(!empty($_POST['email_resend']) or !empty($_GET['force_resend'])){
		// isolate the user creds
		$utoken = stripslashes(htmlspecialchars($_COOKIE['authentication']));
		// lfdu = look for da user
		$lfdu = mysqli_query($ctds, "SELECT * FROM `accounts` WHERE `authentication`='". $_COOKIE['authentication'] ."'");
		$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
		// if there is no error...
		if(!is_bool($lfdu)){
			// if a user matching the ID exists...
			if(mysqli_num_rows($lfdu) != 0){
				$rng = mt_rand(111111, 999999);
				$aceptar = mysqli_query($ctds, "UPDATE `accounts` SET `latest2fa`='". $rng ."' WHERE `authentication`='". $lfdu_RSLT['authentication'] ."'");

				$to = stripslashes(htmlspecialchars($lfdu_RSLT['email']));
				$subject = "Verify Chatrooms Email";
				$txt = "It looks like you have requested to create an account for this Chatroom. The account's username is " . stripslashes(htmlspecialchars($lfdu_RSLT['username'])) . ".\r\n\r\n
				The verification code is " . $rng . ". If you did not request to create an account for this Chatroom, ignore this message.\r\n\r\n
				Either way, please do not reply to this message. Thank you.";
				$headers = "From: system@chatrooms.epicgamer.org";

				mail($to,$subject,$txt,$headers);
				header("Location: /chatrooms/login/step2/?loginfail&reason=A 2FA code has been sent to your email. Check your inbox or spam folder!");
			}
			else{
				// return warning telling the client that the user is unexistent
				header("Location: /chatrooms/login/step2/?loginfail&reason=The account you're trying to verify doesn't exist!");
			}
		}
		else{
			// return warning telling the client that the user is unexistent
			header("Location: /chatrooms/login/step2/?loginfail&reason=The account you're trying to verify doesn't exist!");
		}
	}
	//header("Location: login.php?loginfail&reason=This feature has been permanently disabled");
?>
<html>
	<head>
		<title>Chatrooms</title>
		<script src='/jquery-3.6.0.min.js'></script>
		<link href="/bootstrap.min.css" rel="stylesheet">
		<script src="/bootstrap.bundle.min.js"></script>
		<link href="/ChatroomsClient.css" rel="stylesheet">
		<link href="" id='darkmode' rel="stylesheet">
		<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
		<meta http-equiv="Pragma" content="no-cache">
		<meta http-equiv="Expires" content="0">

		<script>
			var token = "<?php echo($h); ?>";
			var time = "<?php echo(time()); ?>";
			function init(){
				/*$.get("/api/ping/", 
					function(data, status){
						if(data[0] == "pong"){
							console.log("Connected to server!"); 
							addMessage("System", "Welcome to the Chatrooms Experience!", 0, 0, true);
						}
						else{
							console.error("FUCK"); 
							addMessage("System", "<span style='color: red;'>Failed to connect to Chatrooms!</span>", 0, 0, true);
							addMessage("System", "<span style='color: red;'>The server responded: " + data[0] + "</span>", 0, 0, true);
						}
					}
				);*/
			}
		</script>
		<meta property="og:type" content="website">
	 	<meta property="og:description" content="Click on this link to join the party today.">
	 	<meta property="og:title" content="You have been invited to a Chatroom!">
	 	<meta property="og:url" content="https://chatrooms.epicgamer.org">
	</head>
<body>
<nav class="navbar navbar-expand-sm navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="/">Chatrooms</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mynavbar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mynavbar">
      <ul class="navbar-nav me-auto">
      </ul>
      <div class="d-flex">
      </div>
    </div>
  </div>
</nav>
<center>
	<?php if(isset($_GET['loginfail'])){echo('
	<div class="alert alert-danger">
  		'. stripslashes(htmlspecialchars($_GET['reason'])) .'
	</div>');} ?>
	<br><br>
	<img src='/assets/wavingfella.png' width='256' height='256'>
	<h1>Check Your Email!</h1>
	<p>You should be expecting an email with the key that you need to insert to verify your email.</p>
	<form action='./' method='POST'>
      		<div class="form-floating mb-3 mt-3">
     			<input type="text" min='6' max='6' class="form-control" id="LUSER" placeholder="Enter key..." name="Lkey">
      			<label>Key</label>
		</div>
  		<input type="submit" name='email_confirm' value='Accept' class="btn btn-primary btn-block">
      	</form>
	<form action='2fa_step2.php' method='POST'>
		<input type="submit" name='email_resend' value='Resend Email' class="btn btn-secondary btn-block">
	</form>
</center>
</body>
</html>
