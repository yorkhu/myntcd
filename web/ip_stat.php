<?php
if ((count($_GET) == 1)) {
	$ip = htmlspecialchars($_GET['ip']);
	$site = 'ip_stat.php?ip='.$ip;
	require_once('stat.php');
}
?>