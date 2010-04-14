<?php
$cmd = "passwd\n";
$cmd .= "ena\n";
$cmd .= "PWD\n";
$cmd .= "conf t\n";
$cmd .= "no ip access-list extended blacklist\n";
$cmd .= "ip access-list extended blacklist\n";
if ($result->numRows() > 0) {
	while ($r =& $result->fetchRow()) {
		$cmd .= 'deny ip host '.$r["ips"]." any\n";
	}
}

$cmd .= "permit ip any any\n";
$cmd .= "permit gre any any\n";
$cmd .= "permit icmp any any\n";
$cmd .= "end\n";
$cmd .= "wri\n";
$cmd .= "exit\n";
exec("/bin/echo \"$cmd\" | /bin/nc 10.0.0.1 23 ", $error );
?>