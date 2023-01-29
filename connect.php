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
 
	if(isset($_GET['i_want_to_die'])){
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

	$emotelist = array(
		":nerd:" => "/assets/emotes/nerd.png", 
		":heheheha:" => "/assets/emotes/heheheha.gif", 
		":copium:" => "/assets/emotes/copium.png", 
		":clashroyale:" => "/assets/emotes/clashroyale.png",
		":okayge:" => "/assets/emotes/okayge.png",
	);

	$scpath = file_get_contents("/opt/lampp/htdocs/serverproperties.json");
	$serverconfig = json_decode($scpath, true);
/*
	$emkeyscount = 0;
					foreach($emotelist as $emotelist2){
						$emotekeys = array_keys($emotelist);
						echo($emotekeys[1]);
						$actual_mesg = str_replace($emotekeys[$emkeyscount], "<img src=\'". stripslashes(htmlspecialchars($emotelist2)) ."\' width=\'48\' height=\'48\'>", "placeholder");
						$emkeyscount++;
					}*/
	//echo $actual_mesg;

?>
