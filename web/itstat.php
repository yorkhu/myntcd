<?php

if ((count($_POST) == 6)) {
	require_once('/etc/myntcd/myntcd.conf');
	require_once('include/lib.php');
	require_once('DB.php');

	$db =& DB::connect($dsn);
	if (DB::isError($db)) {
		die($db->getMessage());
	}
	$db->setFetchMode(DB_FETCHMODE_ASSOC);

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

	hp_header("$title");

	 if (count($_GET) == 0) {
		echo "<div class=\"date\">Traffic Data: $hp_start - $hp_end</div>\n";
		new_search("itstat.php");

		echo "<div class=\"block\">\n";
		start_table("IP Ranges:", "class=\"left\"", "<BR>", "Total", "IN", "OUT", "OUT%");

		switch ($mod) {
			case "1":
				$SQL = "SELECT SUM(in_byte) AS in_byte, SUM(out_byte) AS out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
				$SQL .= " FROM rrd_daily WHERE date>=? AND date<=?";
				$data = Array($db_start,$db_end);
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
				$SQL = "INSERT INTO tmp_table SELECT '0.0.0.0', SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_now";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "INSERT INTO tmp_table SELECT '0.0.0.0', SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_hourly";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "INSERT INTO tmp_table SELECT '0.0.0.0', SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_daily WHERE date>='$db_start'";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "SELECT SUM(in_byte) AS in_byte, SUM(out_byte) AS out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
				$SQL .= " FROM tmp_table";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
			break;
		}
		$row =& $result->fetchRow();
		new_line("Total:", $row["bsum"], $row["in_byte"], $row["out_byte"]);

		switch ($mod) {
			case "1":
				#ONLY MYSQL
				$SQL = "SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))) as ip,";
				$SQL .= " SUM(in_byte) AS in_byte, SUM(out_byte) AS out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
				$SQL .= " FROM rrd_daily WHERE date>='$db_start' AND date<='$db_end'";
				$SQL .= " GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
				$result =& $db->query($SQL);
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
				$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip)))";
				$SQL .= " , SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_now GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),";
				$SQL .= " SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_hourly GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),";
				$SQL .= " SUM(in_byte), SUM(out_byte)";
				$SQL .= " FROM rrd_daily WHERE date>='$db_start'";
				$SQL .= " GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
				$SQL = "SELECT ip ,SUM(in_byte) AS in_byte, SUM(out_byte) AS out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
				$SQL .= " FROM tmp_table GROUP BY ip";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
			break;
		}
		if ($result->numRows()>0) {
			while ($row =& $result->fetchRow()) {
				new_line($row["ip"].'.0', $row["bsum"], $row["in_byte"], $row["out_byte"]);
			}
		}
		end_table();
		
		start_table("TOP 20.", "class=\"right\"", "IP", "Total", "IN", "OUT", "OUT%");
		switch ($mod) {
			case "1":
				$SQL = "SELECT ip, SUM(in_byte) AS in_byte, SUM(out_byte) AS out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
				$SQL .= " FROM rrd_daily WHERE date>='$db_start' AND date<='$db_end'";
				$SQL .= " GROUP BY ip ORDER BY bsum DESC";
				$result =& $db->limitQuery($SQL, 0, 20);
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
				$SQL = "SELECT ip, SUM(in_byte) AS in_byte, SUM(out_byte) AS out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
				$SQL .= " FROM tmp_table GROUP BY ip ORDER BY bsum DESC";
				$result =& $db->limitQuery($SQL, 0, 20);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
			break;
		}
		if ($result->numRows()>0) {
			while ($row =& $result->fetchRow()) {
				new_line("<a href=\"ip_stat.php?ip=".$row["ip"]."\">".$row["ip"]."</a>", $row["bsum"], $row["in_byte"], $row["out_byte"]);
			}
		}
		end_table();
		echo "	</div>\n";
	} elseif (count($_GET) == 1) {
		$ip = htmlspecialchars($_GET['ip']);
		
		if ($ip != "top") {
			echo "<div class=\"name\">$ip.0</div>\n";
			echo "<div class=\"date\">$hp_start - $hp_end</div>\n";
			new_search("itstat.php?ip=$ip");

			start_table("", "", "IP", "Total", "IN", "OUT", "OUT%");
			switch ($mod) {
				case "1":
					$SQL = "SELECT ip, SUM(in_byte) AS in_byte, SUM(out_byte) AS out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
					$SQL .= " FROM rrd_daily WHERE date>='$db_start' AND date<='$db_end' AND ip LIKE '$ip%'";
					$SQL .= " GROUP BY ip ORDER BY bsum DESC";
					$result =& $db->query($SQL);
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
					$SQL .= " FROM rrd_now WHERE ip LIKE '$ip%' GROUP BY ip";
					$result =& $db->query($SQL);
					if (DB::isError($result)) {
						die($result->getMessage());
					}
					$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
					$SQL .= " FROM rrd_hourly WHERE ip LIKE '$ip%' GROUP BY ip";
					$result =& $db->query($SQL);
					if (DB::isError($result)) {
						die($result->getMessage());
					}
					$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
					$SQL .= " FROM rrd_daily WHERE date>'$db_start' AND ip LIKE '$ip%' GROUP BY ip";
					$result =& $db->query($SQL);
					if (DB::isError($result)) {
						die($result->getMessage());
					}
					$SQL = "SELECT ip, SUM(in_byte) AS in_byte, SUM(out_byte) AS out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
					$SQL .= " FROM tmp_table GROUP BY ip ORDER BY bsum DESC";
					$result =& $db->query($SQL);
					if (DB::isError($result)) {
						die($result->getMessage());
					}
				break;
			}
			if ($result->numRows()>0) {
				while ($row =& $result->fetchRow()) {
					new_line("<a href=\"ip_stat.php?ip=".$row["ip"]."\">".$row["ip"]."</a>", $row["bsum"], $row["in_byte"], $row["out_byte"]);
				}
			}
			end_table();
		}
	}
	hp_footer();
	$db->disconnect();
}
?>
