<?php
require_once('/etc/myntcd/myntcd.conf');
require_once('include/lib.php');
require_once('DB.php');

$db =& DB::connect($dsn);
if (DB::isError($db)) {
	die($db->getMessage());
}
$db->setFetchMode(DB_FETCHMODE_ASSOC);
 
hp_header("Top List");

if ((count($_GET) == 0) && (count($_POST) == 0)) {
	$d_start = date("Y.m.d H:00:00");
	$m = date("i");
	$m = $m-($m%5);
	if ($m<="5") {
		$m = "0$m";
	}
	$d_end  = date("H:")."$m:00";
	
	echo '<div class="name">Top List</div>'."\n";
	
	new_search("top.php");
	
	echo '<div class="date">Top List: '.$d_start.' - '.$d_end."</div>\n";
	echo "<div class=\"block\">\n";
	
	/*
	
		Top 20 IN
	
	*/
		start_table('TOP 20. IN', 'class="left"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
	
		$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
		$SQL .= " FROM rrd_now GROUP BY ip ORDER BY ? DESC";
		$data = array('in_byte');
		$result =& $db->limitQuery($SQL, 0, 20, $data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		
		while ($row =& $result->fetchRow()) {
			new_line('<a href="ip_stat.php?ip='.$row["ip"].'">'.$row["ip"].'</a>', $row["bsum"], $row["in_byte"], $row["out_byte"]);
		}
		end_table($f);
	
	/*
		TOP 20 OUT
	*/
	
		start_table('TOP 20. OUT', 'class="right"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
		$data = array('out_byte');
		$result =& $db->limitQuery($SQL, 0, 20, $data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		while ($row =& $result->fetchRow()) {
			new_line('<a href="ip_stat.php?ip='.$row["ip"].'">'.$row[ip].'</a>', $row["bsum"], $row["in_byte"], $row["out_byte"]);
		}
		end_table($f);
	
	echo "</div>\n";
	
	$SQL = "CREATE TEMPORARY TABLE tmp_table";
	$SQL .= " ( `ip` varchar(15) default NULL, `in_byte` bigint(20) unsigned NOT NULL default '0',";
	$SQL .= "  `out_byte` bigint(20) unsigned NOT NULL default '0')";
	$result =& $db->query($SQL);
	
	#NAP FORGALMA
	$d_start = date("Y.m.d 00:00:00");
	echo '<div class="date">Top List: '.$d_start.' - '.$d_end."</div>\n";
	echo "<div class=\"block\">\n";
		start_table('TOP 20. IN', 'class="left"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
	
		$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte) FROM rrd_now GROUP BY ip";
		$result =&$db->query($SQL);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte) FROM rrd_hourly GROUP BY ip";
		$result =&$db->query($SQL);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		
		$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
		$SQL .= " FROM tmp_table GROUP BY ip ORDER BY ? DESC";
		$data = array('in_byte');
		$result =& $db->limitQuery($SQL, 0, 20, $data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		while ($row =& $result->fetchRow()) {
			new_line('<a href="ip_stat.php?ip='.$row["ip"].'">'.$row["ip"].'</a>', $row["bsum"], $row["in_byte"], $row["out_byte"]);
		}
		end_table();
		
		start_table('TOP 20. OUT', 'class="right"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
	
		$data = array('out_byte');
		$result =& $db->limitQuery($SQL, 0, 20, $data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		while ($row =& $result->fetchRow()) {
			new_line('<a href="ip_stat.php?ip='.$row["ip"].'">'.$row["ip"].'</a>', $row["bsum"], $row["in_byte"], $row["out_byte"]);
		}
		
		end_table();
		
	echo "</div>\n";
	
	#HONAP FORGALMA
	$d_start = date("Y.m.01 00:00:00");
	$d_db = date("Y-m-01 00:00:00");
	$d_end  = date("Y.m.d H:")."$m:00";
	
	echo '<div class="date">Top List: '.$d_start.' - '.$d_end."</div>\n";
	echo "<div class=\"block\">\n";
		start_table('TOP 20. IN', 'class="left"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
		$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte) FROM rrd_daily WHERE date>'$d_db' GROUP BY ip";
		$result =&$db->query($SQL);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		
		$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
		$SQL .= " FROM tmp_table GROUP BY ip ORDER BY ? DESC";
		$data = array('in_byte');
		$result =& $db->limitQuery($SQL, 0, 20, $data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
		
		
		while ($row =& $result->fetchRow()) {
			new_line('<a href="ip_stat.php?ip='.$row["ip"].'">'.$row["ip"].'</a>', $row["bsum"], $row["in_byte"], $row["out_byte"]);
		}
		end_table();
	
		start_table('TOP 20. OUT', 'class="right"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
	
		$data = array('out_byte');
		$result =& $db->limitQuery($SQL, 0, 20, $data);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
	
		
		while ($row =& $result->fetchRow()) {
			new_line('<a href="ip_stat.php?ip='.$row["ip"].'">'.$row["ip"].'</a>', $row["bsum"], $row["in_byte"], $row["out_byte"]);
		}
		
		$SQL =  "DELETE FROM tmp_table";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
	
		end_table();
	echo "</div>\n";
} elseif ((count($_GET) == 0) && (count($_POST) == 6)) {
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
	if (($hp_end >= date("Y.m.d")) AND ($hp_start <= date("Y.m.d"))) {
		$mod = 2;
		$SQL = "CREATE TEMPORARY TABLE tmp_table";
		$SQL .= " ( `ip` varchar(15) default NULL, `in_byte` bigint(20) unsigned NOT NULL default '0',";
		$SQL .= "  `out_byte` bigint(20) unsigned NOT NULL default '0')";
		$result =& $db->query($SQL);
	}

	#TOP LIST
	echo '<div class="date">Top List: '.$hp_start.' - '.$hp_end."</div>\n";
	new_search("top.php");
	echo "<div class=\"block\">\n";
	start_table('TOP 20. IN', 'class="left"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
	switch ($mod) {
		case "1":
			$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
			$SQL .= " FROM rrd_daily WHERE date>='$db_start' AND date<='$db_end'";
			$SQL .= " GROUP BY ip ORDER BY ? DESC";
			$data = array('in_byte');
			$result =& $db->limitQuery($SQL, 0, 20, $data);
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
			$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM rrd_now GROUP BY ip";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				die($result->getMessage());
			}
			$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM rrd_hourly GROUP BY ip";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				die($result->getMessage());
			}
			$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM rrd_daily WHERE date>'$db_start' GROUP BY ip";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				die($result->getMessage());
			}
			$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
			$SQL .= " FROM tmp_table GROUP BY ip ORDER BY ? DESC";
			$data = array('in_byte');
			$result =& $db->limitQuery($SQL, 0, 20, $data);
			if (DB::isError($result)) {
				die($result->getMessage());
			}
		break;
	}
	if ($result->numRows()>0) {
		while ($row =& $result->fetchRow()) {
			new_line('<a href="ip_stat.php?ip='.$row["ip"].'">'.$row["ip"].'</a>', $row["bsum"], $row["in_byte"], $row["out_byte"]);
		}
	}
	end_table();
	start_table('TOP 20. OUT', 'class="right"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
	$data = array('out_byte');
	$result =& $db->limitQuery($SQL, 0, 20, $data);
	if (DB::isError($result)) {
		die($result->getMessage());
	}
	if ($result->numRows()>0) {
		while ($row =& $result->fetchRow()) {
			new_line('<a href="ip_stat.php?ip='.$row["ip"].'">'.$row["ip"].'</a>', $row["bsum"], $row["in_byte"], $row["out_byte"]);
		}
	}
	end_table();
	echo "	</div>\n";
}

hp_footer();
$db->disconnect();
?>
