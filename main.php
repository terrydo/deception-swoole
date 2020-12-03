<?php 

	require_once 'deception-server.php';

	$server = new DeceptionServer("0.0.0.0", 9501);

	$server->start();
	
?>
