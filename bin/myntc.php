#! /usr/bin/php
<?php
$basedir = dirname(__FILE__);
require_once($basedir.'/lib.php');

/**
 * Create rrd file
 */ 
function cgraph($ip, $stime) {
	global $config;
	$error = array();
	exec($config['rrd_cmd'].' create '.$config['rrd_dir'].$ip.".rrd --start $stime --step ".$config['save_interval']." DS:in:GAUGE:600:U:U DS:out:GAUGE:600:U:U RRA:AVERAGE:0.5:1:6000 RRA:AVERAGE:0.5:60:700 RRA:AVERAGE:0.5:240:775 RRA:AVERAGE:0.5:2880:797 RRA:MAX:0.5:1:6000 RRA:MAX:0.5:60:700 RRA:MAX:0.5:240:775 RRA:MAX:0.5:2880:797", $error);
}

/**
 * Letezik-e a pid.
 */
function getpid($pid) {
	$ps=shell_exec("ps p ".$pid);
	$ps=explode("\n", $ps);
	if(count($ps) <= 2) {
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
		die('Already running.');
	}
}

/**
 * Lock file letrehozasa
 */
$f = fopen($lock_file, 'w+');
fwrite($f, getmypid());
fflush($f);
fclose($f);

/**
 * Config file check
 */
$config = read_cfg();

if ( $config ) {
	// Kapcsolodas az SQL szerverhez
	$db =& DB::connect($config['dbs']);
	if (DB::isError($db)) {
		openlog("myntc.php", LOG_PID, LOG_LOCAL0);
		syslog(LOG_ERR, "DB::connect: ".$db->getMessage());
		closelog();
		return false;
	}
	$db->setFetchMode(DB_FETCHMODE_ASSOC);

	// Minden nap adott idoben a napi adatokat athelyezzuk a vegeleges helyukre.
	if (date('H') == $config['hour'] && date('i') == $config['minute']) {
		// Tomoriti az adatokat, minden honap elejen
		if ($config['backup'] && date('Y-m-01') == date('Y-m-d')) {
			exec("tar --remove-files -zcvf ".$config['backupdir']."data".date('Y-m',strtotime(date('Y-m-d').'-1 month')).".tar.gz ".$config['backupdir']."data/\n", $error);
		}

		$date = array();
		// Korabbi nap(ok) adatainak attoltese a vegleges helyukre
		$SQL = "SELECT date FROM now ORDER BY date ASC";
		$result =& $db->limitQuery($SQL, 0, 1);
		if (DB::isError($result)) {
			openlog("myntc.php", LOG_PID, LOG_LOCAL0);
			syslog(LOG_ERR, $SQL.": ".$result->getMessage());
			closelog();
			return false;
		}
		$r =& $result->fetchRow();
		if (date('Y-m-d', strtotime($r['date'])) < date('Y-m-d')) {
			$date['db'] = strtotime($r["date"]);
			$date['start'] = date("Y-m-d 00:00:00", $date['db']);
			$date['end'] = date("Y-m-d 00:00:00");
			$SQL = "INSERT INTO daily (ip, ipr_id, date, type, in_packet, out_packet, in_byte, out_byte)";
			$SQL .= " SELECT ip, ipr_id, DATE(date), type, SUM(in_packet), SUM(out_packet), SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM now WHERE date >= '".$date['start']."' AND date < '".$date['end']."' GROUP BY ip, type, DATE(date) ORDER BY DATE(date), ip";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("myntc.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $SQL.": ".$result->getMessage());
				closelog();
				return false;
			}
			$SQL = "DELETE FROM now WHERE date >= '".$date['start']."' AND date < '".$date['end']."'";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("myntc.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $SQL.": ".$result->getMessage());
				closelog();
				return false;
			}
		}
		// Szamlalok alapertekre allitasa
		if ($db->dbsyntax == 'mysql') {
			$SQL = "ALTER TABLE `tmp` AUTO_INCREMENT = 1";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("myntc.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $SQL.": ".$result->getMessage());
				closelog();
				return false;
			}
			$SQL = "ALTER TABLE `now` AUTO_INCREMENT = 1";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("myntc.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $SQL.": ".$result->getMessage());
				closelog();
				return false;
			}
		}
	}

	// Ha azt szeretnenk, hogy meglegyenek visszamenoleg a fajlok
	if ($config['backup']) {
		exec("cp -R ".$config['dir']." ".$config['backupdir']."\n", $error);
	}

	if ($config['add_sql']) {
		// IP tartomanyok felvetele az adatbazisba
		$epmty = true;
		$exist = false;
		$change = false;
		$SQL = "SELECT * FROM ipranges";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			openlog("myntc.php", LOG_PID, LOG_LOCAL0);
			syslog(LOG_ERR, $SQL.": ".$result->getMessage());
			closelog();
			return false;
		}
		if ($result->numRows() > 0) {
			while ($r =& $result->fetchRow()) {
				$empty = false;
				$ipranges['ipr_id'][] = $r['ipr_id'];
				$ipranges['iprange'][] = $r['iprange'];
				$ipranges['mask'][] = $r['mask'];
			}
		}
		// Ha mar vannak IP tartomanyok az adatbazisba
		if (!$empty) {
			for ($i = 0; $i < count($config['mynetwork']['iprange']); $i++) {
				for ($j = 0; $j < count($ipranges['iprange']); $j++) {
					// Megnezi hogy fel vannak e veve az adatbazisba a tartomanyok
					if (inet_pton($config['mynetwork']['iprange'][$i]) == inet_pton($ipranges['iprange'][$j]) && inet_pton($config['mynetwork']['mask'][$i]) == inet_pton($ipranges['mask'][$j])) {
						$exist = true;
					}
				}
				// Ha nincs, akkor felveszi
				if (!$exist) {
					$SQL = "INSERT INTO ipranges (iprange, mask) VALUES ('".$config['mynetwork']['iprange'][$i]."', '".$config['mynetwork']['mask'][$i]."')";
					$result =& $db->query($SQL);
					if (DB::isError($result)) {
						openlog("myntc.php", LOG_PID, LOG_LOCAL0);
						syslog(LOG_ERR, $SQL.": ".$result->getMessage());
						closelog();
						return false;
					}
					$change = true;
				} else {
					$exist = false;
				}
			}
			// ua. IPv6-ra
			for ($i = 0; $i < count($config['mynetwork6']['iprange']); $i++) {
				for ($j = 0; $j < count($ipranges['iprange']); $j++) {
					if (inet_pton($config['mynetwork6']['iprange'][$i]) == inet_pton($ipranges['iprange'][$j]) && inet_pton($config['mynetwork6']['mask'][$i]) == inet_pton($ipranges['mask'][$j])) {
						$exist = true;
					}
				}
				if (!$exist) {
					$SQL = "INSERT INTO ipranges (iprange, mask) VALUES ('".$config['mynetwork6']['iprange'][$i]."', '".$config['mynetwork6']['mask'][$i]."')";
					$result =& $db->query($SQL);
					if (DB::isError($result)) {
						openlog("myntc.php", LOG_PID, LOG_LOCAL0);
						syslog(LOG_ERR, $SQL.": ".$result->getMessage());
						closelog();
						return false;
					}
					$change = true;
				} else {
					$exist = false;
				}
			}
		} else { // Ha egyetlen taromany sincs az adatbazisba akkor felviszi mindet
			for ($i = 0; $i < count($config['mynetwork']['iprange']); $i++) {
				$SQL = "INSERT INTO ipranges (iprange, mask) VALUES ('".$config['mynetwork']['iprange'][$i]."', '".$config['mynetwork']['mask'][$i]."')";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("myntc.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $SQL.": ".$result->getMessage());
					closelog();
					return false;
				}
			}
			for ($i = 0; $i < count($config['mynetwork6']['iprange']); $i++) {
				$SQL = "INSERT INTO ipranges (iprange, mask) VALUES ('".$config['mynetwork6']['iprange'][$i]."', '".$config['mynetwork6']['mask'][$i]."')";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("myntc.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $SQL.": ".$result->getMessage());
					closelog();
					return false;
				}
			}
			$change = true;
		}
		// Ha valtozott az adatbazis, akkor ujraolvassuk
		if ($change) {
			$ipranges = array();
			$SQL = "SELECT * FROM ipranges";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("myntc.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $SQL.": ".$result->getMessage());
				closelog();
				return false;
			}
			if ($result->numRows() > 0) {
				while ($r =& $result->fetchRow()) {
					$ipranges['ipr_id'][] = $r['ipr_id'];
					$ipranges['iprange'][] = $r['iprange'];
					$ipranges['mask'][] = $r['mask'];
				}
			}
		}
		// Atkonvertaljuk az IP cimet es a maszkot a csomagban talalhato ertekre
		for ($i = 0; $i < count($ipranges['iprange']); $i++) {
			$ipranges['iprange'][$i] = inet_pton($ipranges['iprange'][$i]);
			$ipranges['mask'][$i] = inet_pton($ipranges['mask'][$i]);
		}

		// Konyvtar megnyitasa
		$dir = @opendir($config['dir']);
		if ($dir === false)
		{
			openlog("myntc.php", LOG_PID, LOG_LOCAL0);
			syslog(LOG_ERR, "Can't open ".$config['dir']." directory!");
			closelog();
			return false;
		}
		if ($dir) {
			// Kiuritjuk a tmp tablat
			$SQL = "DELETE FROM tmp";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("myntc.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $SQL.": ".$result->getMessage());
				closelog();
				return false;
			}

			// Beolvassa a konyvtarban levo fileokat
			$files = array();
			while (gettype($file = readdir($dir)) != boolean) { # Azért van így mert lehet 0 nevû fájl is, ami miatt azt false-al tér vissza a readdir.
				if (!is_dir($config['dir'].$file)) {
					if (stristr($file,".dat")) {
						$files[] = $file;
					}
				}
			}
			closedir($dir);
			$filescount = count($files);
			if ($filescount > 0) {
				// Sorbarendezzuk a fajlokat
				for ($i = 0; $i < $filescount; $i++) {
					for ($j = 0; $j < $filescount; $j++) {
						if ($files[$i] < $files[$j]) {
							$temp = $files[$i];
							$files[$i] = $files[$j];
							$files[$j] = $temp;
						}
					}
				}
				// Egyesevel feldolgozzuk a fajlokat
				for ($i = 0; $i < $filescount; $i++) {
					$f = fopen($config['dir'].$files[$i], 'r');
					if ($f === false) {
						openlog("myntc.php", LOG_PID, LOG_LOCAL0);
						syslog(LOG_ERR, "Can't open ".$files[$i]." file!");
						closelog();
						return false;
					}
					if ($f) {
						// Beolvassa a fajl elso soraban talalhato datumot
						$f_d = explode(' ', fgets($f));
						$round_time = date("Y-m-d H:i", $f_d[1]);
						// Adatok betoltese az atmeneti adatbazisba
						while (!feof($f)) {
							$adat = explode(" ", fgets($f));
							
							if ($adat[0] != "") {
								$addr = inet_pton($adat[0]);
								// Megkeresi az IP cimhez a megfelelo tartomanyt
								for ($j = 0; $j < count($ipranges['iprange']); $j++) {
									if (($addr & $ipranges['mask'][$j]) == $ipranges['iprange'][$j]) {
										$iprid = $ipranges['ipr_id'][$j];
									}
								}

								if ($adat[1] + $adat[2] + $adat[3] + $adat[4] != 0) {
									$SQL = "INSERT INTO tmp (ip, ipr_id, date, type, in_packet, out_packet, in_byte, out_byte)";
									$SQL .= " VALUES('$adat[0]', '$iprid', '$round_time', '6', '$adat[1]', '$adat[2]', '$adat[3]', '$adat[4]')";
									$result =& $db->query($SQL);
									if (DB::isError($result)) {
										openlog("myntc.php", LOG_PID, LOG_LOCAL0);
										syslog(LOG_ERR, $SQL.": ".$result->getMessage());
										closelog();
										return false;
									}
								}
								if ($adat[5] + $adat[6] + $adat[7] + $adat[8] != 0) {
									$SQL = "INSERT INTO tmp (ip, ipr_id, date, type, in_packet, out_packet, in_byte, out_byte)";
									$SQL .= " VALUES('$adat[0]', '$iprid', '$round_time', '17', '$adat[5]', '$adat[6]', '$adat[7]', '$adat[8]')";
									$result =& $db->query($SQL);
									if (DB::isError($result)) {
										openlog("myntc.php", LOG_PID, LOG_LOCAL0);
										syslog(LOG_ERR, $SQL.": ".$result->getMessage());
										closelog();
										return false;
									}
								}
								if ($adat[9] + $adat[10] + $adat[11] + $adat[12] != 0) {
									$SQL = "INSERT INTO tmp (ip, ipr_id, date, type, in_packet, out_packet, in_byte, out_byte)";
									$SQL .= " VALUES('$adat[0]', '$iprid', '$round_time', '1', '$adat[9]', '$adat[10]', '$adat[11]', '$adat[12]')";
									$result =& $db->query($SQL);
									if (DB::isError($result)) {
										openlog("myntc.php", LOG_PID, LOG_LOCAL0);
										syslog(LOG_ERR, $SQL.": ".$result->getMessage());
										closelog();
										return false;
									}
								}
								if ($adat[13] + $adat[14] + $adat[15] + $adat[16] != 0) {
									$SQL = "INSERT INTO tmp (ip, ipr_id, date, type, in_packet, out_packet, in_byte, out_byte)";
									$SQL .= " VALUES('$adat[0]', '$iprid', '$round_time', '-1', '$adat[13]', '$adat[14]', '$adat[15]', '$adat[16]')";
									$result =& $db->query($SQL);
									if (DB::isError($result)) {
										openlog("myntc.php", LOG_PID, LOG_LOCAL0);
										syslog(LOG_ERR, $SQL.": ".$result->getMessage());
										closelog();
										return false;
									}
								}
							}
						}
					}
					fclose($f);
				}
				// Korabban felvitt adatok torlese
				$SQL = "DROP TABLE IF EXISTS tmp_table";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("myntc.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $SQL.": ".$result->getMessage());
					closelog();
					return false;
				}

				$SQL = "CREATE TEMPORARY TABLE tmp_table";
				$SQL .= " ( `ip` varchar(39) default NULL, `date` datetime NOT NULL default '0000-00-00 00:00:00')";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("myntc.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $SQL.": ".$result->getMessage());
					closelog();
					return false;
				}

				$SQL = "INSERT INTO tmp_table SELECT ip, date FROM now";
				$SQL .= " GROUP BY ip ORDER BY date DESC";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("myntc.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $SQL.": ".$result->getMessage());
					closelog();
					return false;
				}
				$SQL = "DELETE FROM tmp USING tmp, tmp_table WHERE tmp.date <= tmp_table.date AND tmp.ip = tmp_table.ip";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("myntc.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $SQL.": ".$result->getMessage());
					closelog();
					return false;
				}
				// RRD adatbazis feltoltese
				if ($config['add_rrd']) {
					$SQL = "SELECT ip, date, SUM( in_packet ) AS in_packet, SUM( out_packet ) AS out_packet,";
					$SQL .= " SUM( in_byte ) AS in_byte, SUM( out_byte ) AS out_byte";
					$SQL .= " FROM tmp GROUP BY ip, date ORDER BY date, ip";
					$result =& $db->query($SQL);
					if (DB::isError($result)) {
						openlog("myntc.php", LOG_PID, LOG_LOCAL0);
						syslog(LOG_ERR, $SQL.": ".$result->getMessage());
						closelog();
						return false;
					}
					if ($result->numRows() > 0) {
						while ($r =& $result->fetchRow()) {
							// RRD adatbazis letrehozasa
							if (!is_file($config['rrd_dir'].$r['ip'].'.rrd')) {
								cgraph ($r["ip"], strtotime($r['date']) - $config['save_interval']);
							}
							// legutolso adat ideje
							exec($config['rrd_cmd'].' last '.$config['rrd_dir'].$r['ip'].'.rrd', $error );

							if ($error[0] != '-1') {
								if ($error[count($error)-1] < strtotime($r['date'])) {
									// Forgalomi adatok atlagolasa majd betoltese az adatbazisba
									$in = $r['in_byte'] / $config['save_interval'];
									$out = $r['out_byte'] / $config['save_interval'];
									$timestamp = strtotime($r['date']);
									$out = exec($config['rrd_cmd'].' update '.$config['rrd_dir'].$r['ip'].".rrd $timestamp:$in:$out\n", $err);
								}
							}
							$error = '';
						}
					}
				}
				// Az adatok athelyezese a tenyleges helyere
				$SQL = "INSERT INTO now (ip, ipr_id, date, type, in_packet, out_packet, in_byte, out_byte)";
				$SQL .= " SELECT ip, ipr_id, date, type, SUM( in_packet ), SUM( out_packet ), SUM( in_byte ), SUM( out_byte )";
				$SQL .= " FROM tmp GROUP BY ip, date, type ORDER BY ip";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("myntc.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $SQL.": ".$result->getMessage());
					closelog();
					return false;
				}
				
				$SQL = "DELETE FROM tmp";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("myntc.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $SQL.": ".$result->getMessage());
					closelog();
					return false;
				}

				// feldolgozott fileok torlese
				foreach ($files as $file) {
					unlink($config['dir'].$file);
				}
			}
		}
		// Ha tobb fajlt kellett feldolgozni valamifele leallas miatt, es azok datuma kisebb a mai napnal akkor azokat athelyezzuk a megfelelo helyre
		$date = array();
		// Korabbi nap(ok) adatainak attoltese a vegleges helyukre
		$SQL = "SELECT date FROM now ORDER BY date ASC";
		$result =& $db->limitQuery($SQL, 0, 1);
		if (DB::isError($result)) {
			openlog("myntc.php", LOG_PID, LOG_LOCAL0);
			syslog(LOG_ERR, $SQL.": ".$result->getMessage());
			closelog();
			return false;
		}
		$r =& $result->fetchRow();
		if ((date('Y-m-d', strtotime($r['date'])) < date('Y-m-d')) && $filescount > 1) {
			$date['db'] = strtotime($r["date"]);
			$date['start'] = date("Y-m-d 00:00:00", $date['db']);
			$date['end'] = date("Y-m-d 00:00:00");

			$SQL = "INSERT INTO daily (ip, ipr_id, date, type, in_packet, out_packet, in_byte, out_byte)";
			$SQL .= " SELECT ip, ipr_id, DATE(date), type, SUM(in_packet), SUM(out_packet), SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM now WHERE date >= '".$date['start']."' AND date < '".$date['end']."' GROUP BY ip, type, DATE(date) ORDER BY DATE(date), ip";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("myntc.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $SQL.": ".$result->getMessage());
				closelog();
				return false;
			}
			$SQL = "DELETE FROM now WHERE date >= '".$date['start']."' AND date < '".$date['end']."'";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("myntc.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $SQL.": ".$result->getMessage());
				closelog();
				return false;
			}
		}
	}
	$db->disconnect();
}
unlink($lock_file);

?>