<!DOCTYPE html>
<?php 
	//include("ip.php");

	if(empty($_COOKIE['authentication'])){
		header("Server: CloudSeeker");
		header("Location: login.php");
	}
	else{

		header("Server: CloudSeeker");
		$h = "MissingAccount";
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
	<img src='/assets/happy_fella.png' width='256' height='256'>
	<h1>Thank You!</h1>
	<p>You are all set now. Enjoy your stay!</p>
	<form action='/chatrooms.php' method='GET'>
  		<input type="submit" value='Continue to Chatroom' class="btn btn-primary btn-block">
      	</form>
	<form action='/mchatrooms.php' method='GET'>
		<input type="submit" value='...or click here if you are on mobile' class="btn btn-secondary btn-block">
	</form>
</center>
</body>
</html>
