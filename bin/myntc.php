#! /usr/bin/php4
<?php
$basedir = dirname(__FILE__);
require_once('DB.php');
require_once '/etc/myntcd/myntcd.conf';

function cgraph ($ip, $stime) {
	global $save_interval, $rrddir, $rrd_cmd;
exec($rrd_cmd.' create '.$rrddir.$ip.".rrd --start $stime --step $save_interval DS:in:GAUGE:600:U:U DS:out:GAUGE:600:U:U RRA:AVERAGE:0.5:1:6000 RRA:AVERAGE:0.5:60:700 RRA:AVERAGE:0.5:240:775 RRA:AVERAGE:0.5:2880:797 RRA:MAX:0.5:1:6000 RRA:MAX:0.5:60:700 RRA:MAX:0.5:240:775 RRA:MAX:0.5:2880:797", $error );
}

$y = date('Y-m-d ');
$h = date('H:');
$m = date('i');
$m = $m-($m%5);
if ($m < '10') {
	$m = '0'.$m;
}
$d = $y.$h.$m.':00';

if ($data_add_sql) {
	$db =& DB::connect($dsn);
	if (DB::isError($db)) {
		die($db->getMessage());
	}
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
	$SQL = 'SELECT date FROM rrd_now ORDER BY date DESC';
	$result =& $db->limitQuery($SQL, 0, 1);
	if (DB::isError($result)) {
		die($result->getMessage());
	}
	$db_date =& $result->fetchRow();
	$db_d=strtotime($db_date['date']);
	
	$SQL = "CREATE TEMPORARY TABLE tmp_add_table ( ip varchar(15) default NULL,";
	$SQL .= " date datetime NOT NULL default '0000-00-00 00:00:00', type int(3) NOT NULL default '0',";
	$SQL .= " in_count int(10) unsigned NOT NULL default '0', out_count int(10) unsigned NOT NULL default '0',";
	$SQL .= " in_byte bigint(20) unsigned NOT NULL default '0', out_byte bigint(20) unsigned NOT NULL default '0')";
	$result =& $db->query($SQL);

	if ($dir = @opendir($data_dir)) {
		while ($file = readdir($dir)) {
			if ($file != '.' && $file != '..' && !is_dir($data_dir.'/'.$file)) {
				$f = fopen($data_dir.'/'.$file, 'r');
				$f_d = explode(' ', fgets($f));
				if ($db_d<$f_d[1]) {
					if (($f_d[1]%$save_interval) !=0) {
						$now_d = floor($f_d[1] / $save_interval) * $save_interval + $save_interval;
					} else {
						$now_d = $f_d[1];
					}
					$now_d=date("Y-m-d H:i", $now_d);
					while (!feof($f)) {
						$adat = explode(" ", fgets($f));
						if ($adat[1] + $adat[2] + $adat[3] + $adat[4] != 0) {
							$SQL = "INSERT INTO tmp_add_table (ip, date, type, in_count, out_count, in_byte, out_byte)";
							$SQL .= " VALUES('$adat[0]', '$now_d', '6', '$adat[1]', '$adat[2]', '$adat[3]', '$adat[4]')";
							$result =& $db->query($SQL);
						}
						if ($adat[5]+$adat[6]+$adat[7]+$adat[8] !=0) {
							$SQL = "INSERT INTO tmp_add_table (ip, date, type, in_count, out_count, in_byte, out_byte)";
							$SQL .= " VALUES('$adat[0]', '$now_d', '17', '$adat[5]', '$adat[6]', '$adat[7]', '$adat[8]')";
							$result =& $db->query($SQL);
						}
						if ($adat[9]+$adat[10]+$adat[11]+$adat[12] !=0) {
							$SQL = "INSERT INTO tmp_add_table (ip, date, type, in_count, out_count, in_byte, out_byte)";
							$SQL .= " VALUES('$adat[0]', '$now_d', '1', '$adat[9]', '$adat[10]', '$adat[11]', '$adat[12]')";
							$result =& $db->query($SQL);
						}
						if ($adat[13]+$adat[14]+$adat[15]+$adat[16] !=0) {
							$SQL = "INSERT INTO tmp_add_table (ip, date, type, in_count, out_count, in_byte, out_byte)";
							$SQL .= " VALUES('$adat[0]', '$now_d', '-1', '$adat[13]', '$adat[14]', '$adat[15]', '$adat[16]')";
							$result =& $db->query($SQL);
						}
					}
					fclose($f);
					$SQL = "INSERT INTO rrd_now (ip, date, type, in_count, out_count, in_byte, out_byte)";
					$SQL .= " SELECT ip, date, type, SUM( in_count ), SUM( out_count ), SUM( in_byte ), SUM( out_byte )";
					$SQL .= " FROM tmp_add_table GROUP BY ip, date, type ORDER BY ip";
					$result =& $db->query($SQL);
					$SQL = "DELETE FROM tmp_add_table";
					$result_tmp =& $db->query($SQL);
				} else {
					fclose($f);
				}
				unlink($data_dir.'/'.$file);
			}
		}
		closedir($dir);
	}
	$db->disconnect();
}

$now_d = $db_d + $save_interval;
if ($d <= $now_d) {
	$db =& DB::connect($dsn);
	if (DB::isError($db)) {
		die($db->getMessage());
	}
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
	$SQL = "SELECT ip FROM rrd_now WHERE date='".$db_date["date"]."'";
	$result =& $db->query($SQL);
	if ($result->numRows() > '0') {
		while ($r =& $result->fetchRow()) {
			if (is_file($rrddir.$r["ip"].'.rrd')) {
				exec($rrd_cmd.' last '.$rrddir.$r["ip"].'.rrd', $error );
				if ($error[0] != "-1") {
					if ($error[count($error)-1] != $db_d) {
						$rrd_date=date("Y-m-d H:i", $error[0]);
						$SQL = "SELECT ip, date, SUM(in_byte) as in_byte , SUM(out_byte) as out_byte";
						$SQL .= " FROM rrd_now WHERE ip='".$r["ip"]."' AND date>'$rrd_date' AND date<='".$db_date["date"]."'";
						$SQL .= " GROUP BY ip, date ORDER BY date";
						$result_old =& $db->query($SQL);
						while ($r_old =& $result_old->fetchRow()) {
							$in=$r_old["in_byte"]/$save_interval;
							$out=$_old["out_byte"]/$save_interval;
							$t_t=strtotime($r_old["date"]);
							exec($rrd_cmd.' update '.$rrddir.$r_old["ip"].".rrd $t_t:$in:$out\n", $error);
						}
					}
				}
			}
		}
	}

	$SQL = "SELECT ip, date, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte";
	$SQL .= " FROM rrd_now WHERE date>'".$db_date["date"]."' GROUP BY ip, date ORDER BY ip, date";
	$result =& $db->query($SQL);
	if ($result->numRows()>0) {
		while ($r =& $result->fetchRow()) {
			$in=$r["in_byte"]/$save_interval;
			$out=$r["out_byte"]/$save_interval;
			$t_t=strtotime($r["date"]);
			if (!is_file($rrddir.$r["ip"].".rrd")) {
				$t_s=$t_t-$save_interval;
				cgraph ($r["ip"], $t_s);
			}
			exec($rrd_cmd.' update '.$rrddir.$r["ip"].".rrd $t_t:$in:$out\n", $error);
		}
	}
	$db->disconnect();
}

#Minden Oraban
$m = date("i");
if ($m == "00") {
	$db =& DB::connect($dsn);
	if (DB::isError($db)) {
		die($db->getMessage());
	}
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
	$d = date("Y-m-d H:00:00");
	$d_ph =  date("Y-m-d H:00:00",strtotime("-1 hour"));
	#Regi bent maradt adatok
	
	$SQL = "SELECT date FROM rrd_now WHERE date < '$d' GROUP BY date ORDER BY date";
	$result =& $db->limitQuery($SQL, 0, 1);
	while ($result->numRows()>0) {
		$r =& $result->fetchRow();
		$t=strtotime($r["date"]);
		$t_s=date("Y-m-d H:00:00",$t-($t%3600));
		$t_e=date("Y-m-d H:00:00",$t-($t%3600)+3600);
		$SQL = "INSERT INTO rrd_hourly (ip, date, type, in_count, out_count, in_byte, out_byte)";
		$SQL .= " SELECT ip, '$t_e', type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_now WHERE date >= '$t_s' AND date < '$t_e' GROUP BY ip,  type ORDER BY ip";
		$result =& $db->query($SQL);
		
		$SQL = "DELETE FROM rrd_now WHERE date >= '$t_s' AND date < '$t_e'";
		$result =& $db->query($SQL);
		$SQL = "SELECT date FROM rrd_now WHERE date < '$d' GROUP BY date ORDER BY date";
		$result =& $db->limitQuery($SQL, 0, 1);
	}
	#Az aktualis ora adatai
	$SQL = "SELECT date FROM rrd_now WHERE date >= '$d_ph' AND date < '$d' GROUP BY date";
	$result =& $db->query($SQL);
	if ($result->numRows()>0) {
		$SQL = "INSERT INTO rrd_hourly (ip, date, type, in_count, out_count, in_byte, out_byte)";
		$SQL .= " SELECT ip, '$d', type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_now WHERE date >= '$d_ph' AND date < '$d' GROUP BY ip, type ORDER BY ip";
		$result =& $db->query($SQL);
		$SQL = "DELETE FROM rrd_now WHERE date >= '$d_ph' AND date < '$d'";
		$result =& $db->query($SQL);
	}
	#Minden nap.
	$h = date("H");
	if ($h=="00") {
		$prev_day = date("Y-m-d 00:00:00",strtotime("-1 day"));
		$save_day =  date("Y-m-d 12:00:00",strtotime("-1 day"));
		$now_day = date("Y-m-d 00:00:00");
		#Regi bent maradt adatok
		$SQL = "SELECT date FROM rrd_hourly WHERE date < '$prev_day' GROUP BY date ORDER BY date";
		$result =& $db->limitQuery($SQL, 0, 1);
		while ($result->numRows()>0) {
			$r =& $result->fetchRow();
			$t=strtotime($r["date"]);
			$old_start = date("Y-m-d 00:00:00",$t);
			$old_save = date("Y-m-d 12:00:00",$t);
			$t=strtotime("$t_start");
			$old_end = date("Y-m-d 00:00:00",$t+86400);
			$SQL = "INSERT INTO rrd_daily (ip, date, type, in_count, out_count, in_byte, out_byte)";
			$SQL .= " SELECT ip, '$old_save', type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM rrd_hourly WHERE date >= '$old_start' AND date < '$old_end' GROUP BY ip, type ORDER BY ip";
			$result =& $db->query($SQL);
			$SQL = "DELETE FROM rrd_hourly WHERE date >= '$old_start' AND date < '$old_end'";
			$result =& $db->query($SQL);
			$SQL = "SELECT date FROM rrd_hourly WHERE date <  '$prev_day' GROUP BY date ORDER BY date";
			$result =& $db->limitQuery($SQL, 0, 1);
		}
		#Az aktualis nap adatai
		$SQL = "SELECT date FROM rrd_hourly WHERE date >= '$prev_day' AND date < '$now_day' GROUP BY date ORDER BY date";
		$result =& $db->limitQuery($SQL, 0, 1);
		if ($result->numRows()>0) {
			$SQL = "INSERT INTO rrd_daily (ip, date, type, in_count, out_count, in_byte, out_byte)";
			$SQL .= " SELECT ip, '$save_day', type, SUM(in_count), SUM(out_count), SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM rrd_hourly WHERE date >= '$prev_day' AND date < '$now_day' GROUP BY ip, type ORDER BY ip";
			$result =& $db->query($SQL);
			$SQL = "DELETE FROM rrd_hourly WHERE date >= '$prev_day' AND date < '$now_day'";
			$result =& $db->query($SQL);
		}
	}
	$db->disconnect();
}

#GENERATED HP
if ($create_hp) {
	exec("$basedir/mksite.php");
}

#GENERATED IMG
#Minden oraban
$m = date("i");
if ($m == "00") {
	if ($create_img && $create_all_img) {
		exec("$basedir/mkgraph.php 4");
	} elseif ($create_img) {
		#Minden nap.
		$h = date("H");
		if ($h=="00") {
			exec("$basedir/mkgraph.php 4");
		} elseif ( ($h == "06") || ($h == "12") || ($h == "18") ) {
			exec("$basedir/mkgraph.php -l 2");
		} else {
			exec("$basedir/mkgraph.php -l 1");
		}
	}
}
?>