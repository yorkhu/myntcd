<?php
if (count(debug_backtrace()) == "0") {
	header('Location: index.html');
	die;
}
require_once('/etc/myntcd/myntcd.conf');
require_once('include/lib.php');
require_once('DB.php');

hp_header($title);
echo "<br>\n";
$ok = 0;
$db =& DB::connect($dsn);
if (DB::isError($db)) {
	die($db->getMessage());
}

$db->setFetchMode(DB_FETCHMODE_ASSOC);

if (count($_POST) == 0) {
	$data = Array($ip);	
	$SQL = "SELECT ip FROM rrd_daily WHERE ip=?";
	$result1 =& $db->limitQuery($SQL, 0, 1,$data);
	if (DB::isError($result1)) {
		die($result1->getMessage());
	}

	$SQL = "SELECT ip FROM rrd_hourly WHERE ip=?";
	$result2 =& $db->limitQuery($SQL, 0, 1,$data);
	if (DB::isError($result2)) {
		die($result2->getMessage());
	}

	$SQL = "SELECT ip FROM rrd_now WHERE ip=?";
	$result3 =& $db->limitQuery($SQL, 0, 1,$data);
	if (DB::isError($result3)) {
		die($result3->getMessage());
	}
	if (($result1->numRows()) || ($result2->numRows()) || ($result3->numRows())) {
		$ok = 1;
		echo "<div class=\"name\">$ip Traffic Data</div>";
		new_search($site);
		
		$d_start = date("Y.m.d H:00:00");
		$m = date("i");
		$m = $m-($m%5);
		if ($m < "10") {
			$m = '0'.$m;
		}
		$d_end  = date("H:")."$m:00";
		
		echo "<div class=\"date\">$d_start - $d_end:</div>";
		start_ip_table("Traffic Type", "IN Packet Number", "OUT Packet Number", "Total Packet Number", "IN Byte", "OUT Byte", "Total Byte", "OUT%");
	
		$SQL = "SELECT type, SUM(in_count) AS in_count, SUM(out_count) AS out_count, SUM(in_byte) AS in_byte,";
		$SQL .= " SUM(out_byte) AS out_byte, SUM(in_count)+SUM(out_count) as csum, SUM(in_byte)+SUM(out_byte) as bsum";
		$SQL .= " FROM rrd_now WHERE ip=? GROUP BY type ORDER BY bsum DESC";
		$result =& $db->query($SQL,$data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		$total[1] = $total[2] = $total[3] = $total[4] = $total[5] = $total[6] = 0;
		while ($row =& $result->fetchRow()) {
			new_ip_line($row[type], $row[in_count], $row[out_count], $row[in_byte], $row[out_byte], $row[csum], $row[bsum]);
			$total[1] = $total[1] + $row[in_count];
			$total[2] = $total[2] + $row[out_count];
			$total[3] = $total[3] + $row[in_byte];
			$total[4] = $total[4] + $row[out_byte];
			$total[5] = $total[5] + $row[csum];
			$total[6] = $total[6] + $row[bsum];
		}
		new_ip_line("Total:", $total[1], $total[2], $total[3], $total[4], $total[5], $total[6]);
		end_table();
		$d_start = date("Y.m.d 00:00:00");
		echo "<div class=\"date\">$d_start - $d_end:</div>";
		start_ip_table("Traffic Type", "IN Packet Number", "OUT Packet Number", "Total Packet Number", "IN Byte", "OUT Byte", "Total Byte", "OUT%");
	
		$SQL = "CREATE TEMPORARY TABLE tmp_table";
		$SQL .= " ( `type` int(3), `in_count` int(10) unsigned NOT NULL default '0',";
		$SQL .= " `out_count` int(10) unsigned NOT NULL default '0',";
		$SQL .= " `in_byte` bigint(20) unsigned NOT NULL default '0',";
		$SQL .= " `out_byte` bigint(20) unsigned NOT NULL default '0')";
		$result =& $db->query($SQL);

		$SQL = "INSERT INTO tmp_table SELECT type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_now WHERE ip=? GROUP BY type";
		$data = Array($ip);
		$result =&$db->query($SQL,$data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		$SQL = "INSERT INTO tmp_table SELECT type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_hourly WHERE ip=? GROUP BY type";
		$data = Array($ip);
		$result =&$db->query($SQL,$data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}

		$SQL = "SELECT type, SUM(in_count) AS in_count, SUM(out_count) AS out_count, SUM(in_byte) AS in_byte,";
		$SQL .= " SUM(out_byte) AS out_byte, SUM(in_count)+SUM(out_count) as csum, SUM(in_byte)+SUM(out_byte) as bsum";
		$SQL .= " FROM tmp_table GROUP BY type ORDER BY bsum DESC";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		$total[1] = $total[2] = $total[3] = $total[4] = $total[5] = $total[6] = 0;
		while ($row =& $result->fetchRow()) {
			new_ip_line($row[type], $row[in_count], $row[out_count], $row[in_byte], $row[out_byte], $row[csum], $row[bsum]);
			$total[1] = $total[1] + $row[in_count];
			$total[2] = $total[2] + $row[out_count];
			$total[3] = $total[3] + $row[in_byte];
			$total[4] = $total[4] + $row[out_byte];
			$total[5] = $total[5] + $row[csum];
			$total[6] = $total[6] + $row[bsum];
		}
		new_ip_line("Total:", $total[1], $total[2], $total[3], $total[4], $total[5], $total[6]);
		end_table();

		$d_start = date("Y.m.01 00:00:00");
		$m = date("i");
		$m = $m-($m%5);
		if ($m<"10") {
			$m = "0$m";
		}
		$d_end  = date("Y.m.d H:")."$m:00";
		$d_db = date("Y-m-01 00:00:00");
		
		echo "<div class=\"date\">$d_start - $d_end:</div>";
		start_ip_table("Traffic Type", "IN Packet Number", "OUT Packet Number", "Total Packet Number", "IN Byte", "OUT Byte", "Total Byte", "OUT%");
		$SQL = "INSERT INTO tmp_table SELECT type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_daily WHERE ip=?  AND date>? GROUP BY type";
		$data = Array($ip,$d_db);
		$result =&$db->query($SQL,$data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}

		$SQL = "SELECT type, SUM(in_count) AS in_count, SUM(out_count) AS out_count, SUM(in_byte) AS in_byte,";
		$SQL .= " SUM(out_byte) AS out_byte, SUM(in_count)+SUM(out_count) as csum, SUM(in_byte)+SUM(out_byte) as bsum";
		$SQL .= " FROM tmp_table GROUP BY type ORDER BY bsum DESC";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		$total[1] = $total[2] = $total[3] = $total[4] = $total[5] = $total[6] = 0;
		while ($row =& $result->fetchRow()) {
			new_ip_line($row[type], $row[in_count], $row[out_count], $row[in_byte], $row[out_byte], $row[csum], $row[bsum]);
			$total[1] = $total[1] + $row[in_count];
			$total[2] = $total[2] + $row[out_count];
			$total[3] = $total[3] + $row[in_byte];
			$total[4] = $total[4] + $row[out_byte];
			$total[5] = $total[5] + $row[csum];
			$total[6] = $total[6] + $row[bsum];
		}
		new_ip_line("Total:", $total[1], $total[2], $total[3], $total[4], $total[5], $total[6]);
		end_table();
	} else {
		echo "Not found $ip IP address.\n";
	}
} elseif (count($_POST) == 6) {
	$start_y = htmlspecialchars($_POST['start_y']);
	$start_m = htmlspecialchars($_POST['start_m']);
	$start_d = htmlspecialchars($_POST['start_d']);
	$end_y = htmlspecialchars($_POST['end_y']);
	$end_m = htmlspecialchars($_POST['end_m']);
	$end_d = htmlspecialchars($_POST['end_d']);

	$db_start = "$start_y-$start_m-$start_d 00:00:00";
	$db_end = "$end_y-$end_m-$end_d 00:00:00";
	$hp_start = "$start_y.$start_m.$start_d";
	$hp_end = "$end_y.$end_m.$end_d";

	if ($db_start > $db_end) {
		$db_end = "$start_y-$start_m-$start_d 00:00:00";
		$db_start = "$end_y-$end_m-$end_d 00:00:00";
		$hp_end = "$start_y.$start_m.$start_d";
		$hp_start = "$end_y.$end_m.$end_d";
	}

	$mod = 1;
	if ($db_start == $db_end) {
		$db_start = "$start_y-$start_m-$start_d 00:00:00";
		$db_end = "$end_y-$end_m-$end_d 23:00:00";
	}
	$SQL = "SELECT ip FROM rrd_daily WHERE ip=?";
	$data = Array($ip);
	$result1 =& $db->limitQuery($SQL, 0, 1,$data);
	if (DB::isError($result1)) {
		die($result1->getMessage());
	}

	$SQL = "SELECT ip FROM rrd_hourly WHERE ip=?";
	$result2 =& $db->limitQuery($SQL, 0, 1,$data);
	if (DB::isError($result2)) {
		die($result2->getMessage());
	}

	$SQL = "SELECT ip FROM rrd_now WHERE ip=?";
	$result3 =& $db->limitQuery($SQL, 0, 1,$data);
	if (DB::isError($result3)) {
		die($result3->getMessage());
	}
	if (($result1->numRows()) || ($result2->numRows()) || ($result3->numRows())) {
		$ok = 1;
		if (($hp_end >= date("Y.m.d")) AND ($hp_start <= date("Y.m.d"))) {
			$mod = 2;
			$SQL = "CREATE TEMPORARY TABLE tmp_table";
			$SQL .= " ( `type` int(3), `in_count` int(10) unsigned NOT NULL default '0',";
			$SQL .= " `out_count` int(10) unsigned NOT NULL default '0',";
			$SQL .= " `in_byte` bigint(20) unsigned NOT NULL default '0',";
			$SQL .= " `out_byte` bigint(20) unsigned NOT NULL default '0')";
			$result =& $db->query($SQL);
		}
		echo "<div class=\"name\">$ip Traffic Data</div>";
		new_search($site);
		echo "<div class=\"date\">$hp_start - $hp_end:</div>";
		start_ip_table("Traffic Type", "IN Packet Number", "OUT Packet Number", "Total Packet Number", "IN Byte", "OUT Byte", "Total Byte", "OUT%");
		switch ($mod) {
			case "1":
				$SQL = "SELECT type, SUM(in_count) AS in_count, SUM(out_count) AS out_count, SUM(in_byte) AS in_byte,";
				$SQL .= " SUM(out_byte) AS out_byte, SUM(in_count)+SUM(out_count) as csum, SUM(in_byte)+SUM(out_byte) as bsum";
				$SQL .= " FROM rrd_daily WHERE ip=? AND date>=? AND date<=?'";
				$SQL .= " GROUP BY type ORDER BY bsum DESC";
				$data = Array($ip,$db_start,$db_end);
				$result =& $db->query($SQL,$data);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
			break;
			case "2":
				$SQL = "DELETE FROM tmp_table";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "INSERT INTO tmp_table SELECT type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_now WHERE ip=? GROUP BY type";
				$data = Array($ip);
				$result =& $db->query($SQL,$data);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "INSERT INTO tmp_table SELECT type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_hourly WHERE ip=? GROUP BY type";
				$result =& $db->query($SQL,$data);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "INSERT INTO tmp_table SELECT type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_daily WHERE date>? AND ip=? GROUP BY type";
				$data = Array($db_start,$ip);
				$result =& $db->query($SQL,$data);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "SELECT type, SUM(in_count) AS in_count, SUM(out_count) AS out_count, SUM(in_byte) AS in_byte,";
				$SQL .= " SUM(out_byte) AS out_byte, SUM(in_count)+SUM(out_count) as csum, SUM(in_byte)+SUM(out_byte) as bsum";
				$SQL .= " FROM tmp_table GROUP BY type ORDER BY bsum DESC";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
			break;
		}
		if ($result->numRows()>0) {
			while ($row =& $result->fetchRow()) {
				new_ip_line($row[type], $row[in_count], $row[out_count], $row[in_byte], $row[out_byte], $row[csum], $row[bsum]);
				$total[1] = $total[1] + $row[in_count];
				$total[2] = $total[2] + $row[out_count];
				$total[3] = $total[3] + $row[in_byte];
				$total[4] = $total[4] + $row[out_byte];
				$total[5] = $total[5] + $row[csum];
				$total[6] = $total[6] + $row[bsum];
			}
			new_ip_line("Total:", $total[1], $total[2], $total[3], $total[4], $total[5], $total[6]);
		}
		end_table();
	} else {
		echo "Not found $ip IP address.\n";
	}
}
if ($ok) {
	echo "<BR>\n";
	echo "	<img src=\"./img/$ip-daily.png\" border=\"0\">\n";
	echo "	<img src=\"./img/$ip-weekly.png\" border=\"0\"><BR>\n";
	echo "	<img src=\"./img/$ip-monthly.png\" border=\"0\">\n";
	echo "	<img src=\"./img/$ip-yearly.png\" border=\"0\">\n";
}
hp_footer();
$db->disconnect();
?>
