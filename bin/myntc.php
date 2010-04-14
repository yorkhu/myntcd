#! /usr/bin/php
<?php
$basedir = dirname(__FILE__);
require_once($basedir.'/lib.php');

/** Create rrd file */ 
function cgraph($ip, $stime) {
	global $config;
	$error = array();
	exec($config['rrd_cmd'].' create '.$config['rrd_dir'].$ip.".rrd --start $stime --step ".$config['save_interval']." DS:in:GAUGE:600:U:U DS:out:GAUGE:600:U:U RRA:AVERAGE:0.5:1:6000 RRA:AVERAGE:0.5:60:700 RRA:AVERAGE:0.5:240:775 RRA:AVERAGE:0.5:2880:797 RRA:MAX:0.5:1:6000 RRA:MAX:0.5:60:700 RRA:MAX:0.5:240:775 RRA:MAX:0.5:2880:797", $error);
}

/** Letezik-e a pid. */
function getpid($pid){
	$ps=shell_exec("ps p ".$pid);
	$ps=explode("\n", $ps);
	if(count($ps)<=2){
		return false;
	} else {
		return true;
	}
}

/**
 * Lock file check
 */
if (is_file($lock_file)) {
	$f = fopen($lock_file, 'r');
	$pid = fgets($f);
	fclose($f);
	echo getpid($pid);
	if (getpid($pid)) {
		die('Already runing.');
	}
}
/** Minden nap 00-kor. */
if ( (date("H") == "00") && ( (int) date('i') < 5) ) {
	$config = read_cfg();
	if ( $config ) {
		$db =& DB::connect($config['dbs']);
		if (DB::isError($db)) {
			die($db->getMessage());
		}
		$db->setFetchMode(DB_FETCHMODE_ASSOC);
		
		$now_day = date("Y-m-d 00:00:00");
		/** Korabbi nap(ok) adatainak attoltese a vegleges helyukre */
		$SQL = "SELECT date FROM now WHERE date < '".$now_day."' GROUP BY date ORDER BY date";
		$result =& $db->limitQuery($SQL, 0, 1);
		$date = array();
		while ($result->numRows() > 0) {
			$r =& $result->fetchRow();
	
			$date['db'] = strtotime($r["date"]);
			$date['start'] = date("Y-m-d 00:00:00", $date['db']);
			$date['end'] = date("Y-m-d 00:00:00", strtotime($date['start']) + 86400);
			$date['save'] = date("Y-m-d", $date['db']);
	
			$SQL = "INSERT INTO daily (ip, date, type, in_packet, out_packet, in_byte, out_byte)";
			$SQL .= " SELECT ip, '".$date['save']."', type, SUM(in_packet), SUM(out_packet), SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM now WHERE date >= '".$date['start']."' AND date < '".$date['end']."' GROUP BY ip, type ORDER BY ip";
			$result =& $db->query($SQL);
			$SQL = "DELETE FROM now WHERE date >= '".$date['start']."' AND date < '".$date['end']."'";
			$result =& $db->query($SQL);
			$SQL = "SELECT date FROM now WHERE date < '".$now_day."' GROUP BY date ORDER BY date";
			$result =& $db->limitQuery($SQL, 0, 1);
		}

		/** Szamlalok alapertekre allitasa */
		if ($db->dbsyntax == 'mysql') {
			$SQL = "ALTER TABLE `tmp` AUTO_INCREMENT = 1";
			$result =& $db->query($SQL);
			$SQL = "ALTER TABLE `now` AUTO_INCREMENT = 1";
			$result =& $db->query($SQL);
		}
		$db->disconnect();
	}
}

/** Lock file letrehozasa */
$f = fopen($lock_file, 'w+');
fwrite($f, getmypid());
fflush($f);
fclose($f);
/**
 * Config file check
 */
$config = read_cfg();
if ( $config ) {
	if ($config['add_sql']) {
		/** Kapcsolodas az SQL szerverhez */
		$db =& DB::connect($config['dbs']);
		if (DB::isError($db)) {
			die($db->getMessage());
		}
		$db->setFetchMode(DB_FETCHMODE_ASSOC);

		/** Konyvtar emgnyitasa */
		if ($dir = @opendir($config['dir'])) {
			/** Kiuritjuk a tmp tablat */
			$SQL = "DELETE FROM tmp";
			$result_tmp =& $db->query($SQL);

			/** Beolvassa a konyvtarban levo fileokat.*/
			$files = array();
			while ( ($file = readdir($dir)) && (count($files) < 200) ) {
				if ($file != '.' && $file != '..' && !is_dir($config['dir'].'/'.$file)) {
					$f = fopen($config['dir'].'/'.$file, 'r');
					$files[] = $file;
					/** Beolvassa az elsosorban talalhato datumot */
					$f_d = explode(' ', fgets($f));
		
					/** Intervallum vegenek meghatarozasa */
					if (($f_d[1]%$config['save_interval']) != 0) {
						$round_time = floor($f_d[1] / $config['save_interval']) * $config['save_interval'] + $config['save_interval'];
					} else {
						$round_time = $f_d[1];
					}
					$round_time = date("Y-m-d H:i", $round_time);
					/** Adatok betoltese az atmeneti adatbazisba */
					while (!feof($f)) {
						$adat = explode(" ", fgets($f));
						if ($adat[1] + $adat[2] + $adat[3] + $adat[4] != 0) {
							$SQL = "INSERT INTO tmp (ip, date, type, in_packet, out_packet, in_byte, out_byte)";
							$SQL .= " VALUES('$adat[0]', '$round_time', '6', '$adat[1]', '$adat[2]', '$adat[3]', '$adat[4]')";
							$result =& $db->query($SQL);
						}
						if ($adat[5]+$adat[6]+$adat[7]+$adat[8] !=0) {
							$SQL = "INSERT INTO tmp (ip, date, type, in_packet, out_packet, in_byte, out_byte)";
							$SQL .= " VALUES('$adat[0]', '$round_time', '17', '$adat[5]', '$adat[6]', '$adat[7]', '$adat[8]')";
							$result =& $db->query($SQL);
						}
						if ($adat[9]+$adat[10]+$adat[11]+$adat[12] !=0) {
							$SQL = "INSERT INTO tmp (ip, date, type, in_packet, out_packet, in_byte, out_byte)";
							$SQL .= " VALUES('$adat[0]', '$round_time', '1', '$adat[9]', '$adat[10]', '$adat[11]', '$adat[12]')";
							$result =& $db->query($SQL);
						}
						if ($adat[13]+$adat[14]+$adat[15]+$adat[16] !=0) {
							$SQL = "INSERT INTO tmp (ip, date, type, in_packet, out_packet, in_byte, out_byte)";
							$SQL .= " VALUES('$adat[0]', '$round_time', '-1', '$adat[13]', '$adat[14]', '$adat[15]', '$adat[16]')";
							$result =& $db->query($SQL);
						}
					}
					fclose($f);
				}
			}
			/** Korabban felvitt adatok torlese */
			$SQL = "CREATE TEMPORARY TABLE tmp_table";
			$SQL .= " ( `ip` varchar(15) default NULL, `date` datetime NOT NULL default '0000-00-00 00:00:00')";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				die($result->getMessage());
			}

			$SQL = "INSERT INTO tmp_table SELECT ip, date FROM now";
			$SQL .= " GROUP BY ip ORDER BY date DESC";
			$result =&$db->query($SQL);
			if (DB::isError($result)) {
				die($result->getMessage());
			}
			$SQL = "DELETE FROM tmp USING tmp, tmp_table WHERE tmp.date<=tmp_table.date AND tmp.ip=tmp_table.ip";
			$result =& $db->query($SQL);
			/** RRD adatbazis feltoltese */
			if ($config['add_rrd']) {
				$SQL = "SELECT ip, date, SUM( in_packet ) AS in_packet, SUM( out_packet ) AS out_packet,";
				$SQL .= " SUM( in_byte ) AS in_byte, SUM( out_byte ) AS out_byte";
				$SQL .= " FROM tmp GROUP BY ip, date ORDER BY date, ip";
				$result =& $db->query($SQL);
				if (DB::isError($db)) {
					die($db->getMessage());
				}
				
				if ($result->numRows() > 0) {
					while ($r =& $result->fetchRow()) {
						/** RRD adatbazis letrehozasa */
						if (!is_file($config['rrd_dir'].$r['ip'].'.rrd')) {
							cgraph ($r["ip"], strtotime($r['date'])-$config['save_interval']);
						}
						/** legutolso adat ideje */
						exec($config['rrd_cmd'].' last '.$config['rrd_dir'].$r['ip'].'.rrd', $error );
						if ($error[0] != '-1') {
							if ($error[count($error)-1] < strtotime($r['date'])) {
								/** Forgalomi adatok atlagolasa majd betoltese az adatbazisba */
								$in = $r['in_byte']/$config['save_interval'];
								$out = $r['out_byte']/$config['save_interval'];
								$timestamp = strtotime($r['date']);
								$out = exec($config['rrd_cmd'].' update '.$config['rrd_dir'].$r['ip'].".rrd $timestamp:$in:$out\n", $err);
							}
						}
						$error = '';
					}
				}
			}

			$SQL = "INSERT INTO now (ip, date, type, in_packet, out_packet, in_byte, out_byte)";
			$SQL .= " SELECT ip, date, type, SUM( in_packet ), SUM( out_packet ), SUM( in_byte ), SUM( out_byte )";
			$SQL .= " FROM tmp GROUP BY ip, date, type ORDER BY ip";
			$result =& $db->query($SQL);
			if (DB::isError($db)) {
				die($db->getMessage());
			}
			
			$SQL = "DELETE FROM tmp";
			$result =& $db->query($SQL);

			/** feldolgozott fileok torlese*/
			foreach ($files as $file) {
				unlink($config['dir'].'/'.$file);
			}
		}
	}
	$db->disconnect();
}
unlink($lock_file);

?>