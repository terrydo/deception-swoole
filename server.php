<?php 

	require_once 'deception-server.php';

	$server = new DeceptionServer("0.0.0.0", 9502);

	$server->start();
	
?>