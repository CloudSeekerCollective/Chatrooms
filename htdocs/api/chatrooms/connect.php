<?php
	
	/*
		CHATROOMS SERVER SETTINGS
		this file contains all of the settings for the Chatrooms APIs that you might need.
		modify these values as you wish, to match your database settings or other things
		you might need to fulfill to get Chatrooms working.
		this file also contains some vital functions to keep the APIs fresh and running,
		so you can use some of them if you decide to modify the API code at any point.
		and as always, happy modding! :) - Popular Toppling Jelly
	*/
 
	if(isset($_GET['debug_mode'])){
		ini_set('display_errors', '1');
		ini_set('display_startup_errors', '1');
		error_reporting(E_ALL);
	}
	
	header("Content-Type: application/json");

	$ctds = mysqli_connect("localhost", "root", "rL_3UlKhna*nHTZ2", "chrms_universe");

	function makeQuery(string $query){
		$lfdu = mysqli_query($ctds, $query);
	}

	function returnWarnResponse(string $warning){
		$arr = array("warning" => $warning, "status" => "ok");
		echo(json_encode($arr));
	}

	$scpath = file_get_contents("/opt/lampp/htdocs/serverproperties.json");
	$serverconfig = json_decode($scpath, true);
?>
