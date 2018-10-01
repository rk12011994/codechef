<?php

 $conn = new mysqli('localhost','centralsign','centralsign','initialhere');
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	
?>
