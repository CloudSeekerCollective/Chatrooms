<!DOCTYPE html>
<?php 
	include("../../api/chatrooms/connect.php");
	header("Content-Type: text/html");
	/*if($serverconfig['require_email'] == false){
		header("Location: 2fa_step3.php");
	}*/
	//include("ip.php");

	/*if(empty($_COOKIE['authentication'])){
		header("Server: CloudSeeker");
		$h = "MissingAccount";
		//header("Server: CloudSeeker");
	}
	else{
		header("Server: CloudSeeker");
		header("Location: /chatrooms/wa/");
	}*/

	if(!empty($_POST['Ltoken']) and !empty($_POST['account_renewal_confirm'])){
		// isolate the user creds
		$utoken = stripslashes(htmlspecialchars($POST['Ltoken']));
		echo($utoken);
		// lfdu = look for da user
		$lfdu = mysqli_query($ctds, "SELECT * FROM `accounts` WHERE `authentication`='". $_POST['Ltoken'] ."'");
	
		// if there is no error...
		if(!is_bool($lfdu)){
			// if a user matching the ID exists...
			if(mysqli_num_rows($lfdu) != 0){
				// cache db result
				$lfdu_RSLT = mysqli_fetch_assoc($lfdu);
				// isolate all of these variables JUST IN CASE
				// proceed to redirect user
				if(stripslashes(htmlspecialchars($lfdu_RSLT['authentication'])) == $_POST['Ltoken']){
					if(!empty($_POST['LTpass']) and !empty($_POST['LTCpass'])){
						if($_POST['LTpass'] == $_POST['LTCpass']){
							$accept_password = mysqli_query($ctds, "UPDATE `accounts` SET `password`='". password_hash($_POST['LTpass'], PASSWORD_DEFAULT) ."' WHERE `authentication`='". $_POST["Ltoken"] ."'");
							setcookie("authentication", stripslashes(htmlspecialchars($_POST['Ltoken'])), time() + 1000000, "/");
							header("Location: /");
						}
						else{
							header("Location: ./?loginfail&reason=Passwords do not match!");
						}
					}
					else{
						header("Location: ./?loginfail&reason=Please fill out all of the fields");
					}
					
				}
				else{
					// return warning telling the client that the user is unexistent
					header("Location: ./?loginfail&reason=This account does not exist");
				}
			}
			else{
				// return warning telling the client that the user is unexistent
				header("Location: ./?loginfail&reason=This account does not exist");
			}
		}
		// otherwise...
		else{
			// return warning telling the client that the user is unexistent
			header("Location: ./?loginfail&reason=An internal server error occured, please try again");
		}
		//header("Location: login.php?loginfail&reason=This feature has been permanently disabled");
	}
	elseif(!empty($_POST['Luser']) and !empty($_POST['Lpass']) and !empty($_POST['account_new_login'])){
		$username = stripslashes(htmlspecialchars($_POST['Luser']));
		$password = $_POST['Lpass'];

		$lfdu = mysqli_query($ctds, 'SELECT * FROM `accounts` WHERE `username`="'. $username .'"');
		$strings = mysqli_fetch_assoc($lfdu);
		$attempts = 1;
		if(mysqli_num_rows($lfdu) > 0 and password_verify($password, $strings['password'])){
			setcookie("authentication", $strings['authentication'], time() + 2000000000, "/");
			header("Location: /");
		}
		else{
			// return warning telling the client that the user is unexistent
			header("Location: ./?loginfail&reason=Incorrect credentials, try again");
		}
	}
	elseif(!empty($_POST['Ruser']) and !empty($_POST['Rpass']) and !empty($_POST['RCpass']) and !empty($_POST['account_new_register']) and $serverconfig['allow_registrations'] == true){
		$username = stripslashes(htmlspecialchars($_POST['Ruser']));
		$password = $_POST['Rpass'];
		$passwordconfirm = $_POST['RCpass'];
		if($serverconfig['allow_registrations'] == true and !empty($_POST['Remail'])){
			$email = stripslashes(htmlspecialchars($_POST['Remail']));
		}
		else{
			$email = "";
		}
		$lfdu = mysqli_query($ctds, 'SELECT `username` FROM `accounts`');
		$strings = mysqli_fetch_assoc($lfdu);
		$attempts = 1;
		$stop = false;
		$reason = "Passwords do not match!";
		while($accounts = mysqli_fetch_assoc($lfdu)) {
			if($username == $accounts['username']){
				$stop = true;
				$reason = 'Username already taken!';
				header("Location: ./?loginfail&reason=Username already taken!");
			}
			else{
				if($username == "System"){
					$stop = true;
					$reason = "You will be impersonating the System in your dreams.";
					header("Location: ./?loginfail&reason=You will be impersonating the System in your dreams.");
				}
			}
  		}
		if($stop == false and $password == $passwordconfirm){
			$token = md5($username . $password . time());
			$insert_user = mysqli_query($ctds, "INSERT INTO `accounts`(`username`, `password`, `email`, `id`, `picture`, `creationdate`, `status`, `authentication`, `badges`, `latest2fa`) VALUES ('". $username ."','". password_hash($password, PASSWORD_DEFAULT) ."','','". mysqli_num_rows($lfdu) + 1 ."','','". time() ."','ok','". $token ."','[]','000000')");
			setcookie("authentication", $token, time() + 2000000000, "/");
			if($serverconfig['require_email'] == true){
				header("Location: ./2fa/step1/");
			}
			else{
				header("Location: ./success.php");
			}		
		}
		else{
			header("Location: ./?loginfail&reason=". $reason);
		}
		//header("Location: login.php?loginfail&reason=This feature has been permanently disabled");
	}

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
	<h1>Welcome to <?php if(!empty($serverconfig['server_name'])){echo(stripslashes(htmlspecialchars($serverconfig['server_name'])));}else{echo("Chatrooms");} ?>!</h1>
	<p>Seems like you're new here. Don't worry, we can get you ready!</p> 
	<?php if($serverconfig['allow_registrations'] == true){echo("<button class='btn btn-secondary' data-bs-toggle='modal' data-bs-target='#registerModal'>Register</button>");} ?>
	<button class='btn btn-secondary' data-bs-toggle="modal" data-bs-target="#tokenLoginModal">Claim Account</button><br><br>
	<p><a href='/chatrooms/wa/'>Looking for the login button?</a></p>
<div class="modal fade" id="tokenLoginModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Claim or Restore Account</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal body -->
      <form method='POST'>
      <div class="modal-body">
        <div class="form-floating mb-3 mt-3">
     		<input type="text" class="form-control" id="LUSER" placeholder="Enter key..." required name="Ltoken">
      		<label>Account Key</label>
	</div>
	<div class="form-floating mb-3 mt-3">
		<input type="password" class="form-control" id="LUSER" placeholder="Set a password!" required name="LTpass">
      		<label>Password</label>
	</div>
	<div class="form-floating mb-3 mt-3">
		<input type="password" class="form-control" id="LUSER" placeholder="Confirm your password!" required name="LTCpass">
      		<label>Confirm Password</label>
    	</div>
      </div>

      <!-- Modal footer -->
      <div class="modal-footer">
  		<input type="submit" name='account_renewal_confirm' value='ACCEPT' class="btn btn-primary btn-block">
      </div>
      </form>

    </div>
  </div>
</div>
<div class="modal fade" id="registerModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Register</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal body -->
      <form method='POST'>
      <div class="modal-body">
        <div class="form-floating mb-3 mt-3">
     		<input type="text" class="form-control" id="LUSER" required placeholder="Pick a cool username!" name="Ruser">
      		<label>Username</label>
	</div>
	<div class="form-floating mb-3 mt-3">
		<input type="password" class="form-control" id="LUSER" placeholder="Set a password!" required name="Rpass">
      		<label>Password</label>
	</div>
	<div class="form-floating mb-3 mt-3">
		<input type="password" class="form-control" id="LUSER" placeholder="Confirm your password!" required name="RCpass">
      		<label>Confirm Password</label>
    	</div>
	<p>By proceeding you agree to the <a href='/rules.txt'>rules of this Chatroom</a> and that the server administrators will store this data on probably a server far, far away from you. To erase any data as such, contact the server administrator.</p>
      </div>
      <!-- Modal footer -->
      <div class="modal-footer">
  		<input type="submit" name='account_new_register' value='ACCEPT' class="btn btn-primary btn-block">
      </div>
      </form>

    </div>
  </div>
</div>
<div class="modal fade" id="loginModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Login</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal body -->
      <form action='login.php' method='POST'>
      <div class="modal-body">
	<div class="form-floating mb-3 mt-3">
		<input type="text" class="form-control" id="LUSER" placeholder="Enter username..." name="Luser">
      		<label>Username</label>
	</div>
	<div class="form-floating mb-3 mt-3">
		<input type="password" class="form-control" id="LUSER" placeholder="Enter your password..." name="Lpass">
      		<label>Password</label>
    	</div>
      </div>

      <!-- Modal footer -->
      <div class="modal-footer">
  		<input type="submit" name='account_new_login' value='ACCEPT' class="btn btn-primary btn-block">
      </div>
      </form>

    </div>
  </div>
</div>
</center>
</body>
</html>
