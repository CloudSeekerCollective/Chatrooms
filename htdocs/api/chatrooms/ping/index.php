<?php
	// playing a bit of ping pong?
	// this API endpoint is used to check if this Chatrooms server is available.
	// it is preferred to not modify it.

	header("Content-Type: application/json"); 
	$n = array('pong'); 
	echo(json_encode($n)); 
?>
