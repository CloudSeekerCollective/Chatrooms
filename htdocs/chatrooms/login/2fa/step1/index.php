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
		header("Location: /chatrooms/login/");
	}
	else{

		header("Server: CloudSeeker");
		$h = "MissingAccount";
	}
	
	if(!empty($_POST['email_restart'])){
		// lfdu = look for da user
		$lfdu = mysqli_query($ctds, "SELECT * FROM `accounts` WHERE `authentication`='". $_COOKIE['authentication'] ."'");
		$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
		// if there is no error...
		if(!is_bool($lfdu)){
			// if a user matching the ID exists...
			if(mysqli_num_rows($lfdu) != 0){
				$addemail = mysqli_query($ctds, "UPDATE `accounts` SET `email`='". stripslashes(htmlspecialchars($_POST['Lemail'])) ."' WHERE `authentication`='". $lfdu_RSLT['authentication'] ."'");

				$rng = mt_rand(111111, 999999);
				$aceptar = mysqli_query($ctds, "UPDATE `accounts` SET `latest2fa`='". $rng ."' WHERE `authentication`='". $lfdu_RSLT['authentication'] ."'");

				$to = stripslashes(htmlspecialchars($lfdu_RSLT['email']));
				$subject = "Verify Chatrooms Email";
				$txt = "It looks like you have requested to create an account for " . $serverconfig['server_name'] . " . The account's username is " . stripslashes(htmlspecialchars($lfdu_RSLT['username'])) . ".\n\n
				The verification code is " . $rng . ". If you did not request to create an account for this Chatroom, ignore this message.\n\n
				Either way, please do not reply to this message. Thank you.";
				$headers = "From: system@chatrooms.epicgamer.org";

				mail($to,$subject,$txt,$headers);
				header("Location: /chatrooms/login/2fa/step2/?loginfail&reason=A 2FA code has been sent to your email. Check your inbox or spam folder!");
			}
		}
		else{
			// return warning telling the client that the user is unexistent
			header("Location: ./?loginfail&reason=The account you're trying to verify doesn't exist!");
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
	<div class="alert alert-info">
  		NOTE: We're aware of our system being unable to send emails to Gmail users! We recommend using <a href='https://protonmail.com'>Protonmail</a> for the time being. Not Sponsored.
	</div>
	<?php if(isset($_GET['loginfail'])){echo('
	<div class="alert alert-danger">
  		'. stripslashes(htmlspecialchars($_GET['reason'])) .'
	</div>');} ?>
	<br><br>
	<img src='/assets/wavingfella.png' width='256' height='256'>
	<h1>Verify Your Email!</h1>
	<p>To continue to this Chatroom, you must verify your email. Insert your email here to do that.</p>
	<form action='./' method='POST'>
      		<div class="form-floating mb-3 mt-3">
     			<input type="email" class="form-control" id="LUSER" placeholder="Enter email..." name="Lemail">
      			<label>Email</label>
		</div>
  		<input type="submit" name='email_restart' value='Send Email' class="btn btn-primary btn-block">
      	</form>
</center>
</body>
</html>
