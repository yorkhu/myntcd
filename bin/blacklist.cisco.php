#! /usr/bin/php
<?php
require_once('DB.php');
require_once '/etc/myntcd/myntcd.conf';

$db =& DB::connect($dsn);
if (DB::isError($db)) {
	die($db->getMessage());
}
$db->setFetchMode(DB_FETCHMODE_ASSOC);

$update = 0;
$d_start = date("Y-m-d 00:00:00", strtotime("-$limit[time] day"));
$dm_now = date("i");
if ($dm_now == "00") {
	$update = "1";
} else {
	$d_prev = date("Y-m-d H", strtotime("-5 min"));
	$m = date("i", strtotime("-5 min"));
	if ($m<"10") {
		$m = "0$m";
	}
	$d_prev = "$d_prev:$m:00";

	#blacklist es whitelist modositasanak vizsgalata
	$SQL = "SHOW TABLE STATUS LIKE '%list'";
	$result =& $db->query($SQL);
	while ($r =& $result->fetchRow()) {
		if ($d_prev <= $r["Update_time"]) {
			$update = 1;
		}
	}
}

if ($update) {
	#blacklist elavult elemeinek torlese
	$SQL = " DELETE FROM blacklist";
	$SQL .= " WHERE date<'$d_start' AND date>'0000-00-00 00:00:00'";
	$result =& $db->query($SQL);
	
	#whitelist elavult elemeinek torlese
	$SQL = " DELETE FROM whitelist";
	$SQL .= " WHERE date<'$d_start' AND date>'0000-00-00 00:00:00'";
	$result =& $db->query($SQL);
	
	#Szurolista elkeszitese...
	$SQL = "CREATE TEMPORARY TABLE tmp_table AS";
	$SQL .= " SELECT ip, SUM(out_byte) as out_byte FROM rrd_now GROUP BY ip, type";
	$result =& $db->query($SQL);
	
	$SQL = "INSERT INTO tmp_table SELECT ip, SUM(out_byte) as out_byte FROM rrd_hourly";
	$SQL .= " GROUP BY ip, type";
	$result =& $db->query($SQL);
	
	$d_start = date("Y.m.d 00:00:00", strtotime("-$limit[time] day"));
	
	$SQL = "CREATE TEMPORARY TABLE ip_list AS";
	$SQL .= " SELECT ip FROM rrd_daily";
	$SQL .= " WHERE date>='$d_start' AND out_byte>='$limit[out_byte]' GROUP BY ip";
	$result =& $db->query($SQL);
	
	$SQL = "INSERT INTO ip_list SELECT ip FROM tmp_table";
	$SQL .= " GROUP BY ip HAVING SUM(out_byte) >= '$limit[out_byte]'";
	$result =& $db->query($SQL);
	
	$SQL = "INSERT INTO ip_list SELECT ip FROM blacklist";
	$SQL .= " WHERE date>='$d_start'";
	$result =& $db->query($SQL);
	
	$SQL = "SELECT ip_list.ip AS ips FROM ip_list LEFT JOIN whitelist ON (ip_list.ip = whitelist.ip)";
	$SQL .= " WHERE whitelist.ip is null GROUP BY ip";
	$result =& $db->query($SQL);
	
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
}

$db->disconnect();
?>