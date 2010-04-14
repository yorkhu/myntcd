<?php
if ((count($_GET) == 0)) {
	$ip = $_SERVER['REMOTE_ADDR'];
	$site='mystat.php';
	require_once('stat.php');
}
?>

