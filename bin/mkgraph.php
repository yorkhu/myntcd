#! /usr/bin/php4
<?php

require_once('DB.php');
include '/etc/myntcd/myntcd.conf';

// Grafikont keszito fugveny
function cgraph ($img, $ip, $stime, $etime) {
	global $imgdir, $rrddir, $rrd_cmd;

	$img="$imgdir$ip-$img.png";
	if (is_file($rrddir.$ip.'.rrd')) {
		$rrdcmd = "$rrd_cmd graph $img --imgformat 'PNG'";
#		$rrdcmd .= " --start '$stime' --end '$etime'";
		$rrdcmd .= " --start '$stime'";
		$rrdcmd .= " --vertical-label 'byte / sec' --title $ip";
		$rrdcmd .= " --alt-y-mrtg --lazy -c \"MGRID#ee0000\"";
		$rrdcmd .= " -c \"GRID#000000\" 'DEF:A=$rrddir$ip.rrd:in:AVERAGE'";
		$rrdcmd .= " 'DEF:B=$rrddir$ip.rrd:out:AVERAGE' 'CDEF:C=A,B,+'";
		$rrdcmd .= " 'LINE1:A#00FF00:In' 'GPRINT:A:MIN:(min=%.0lf %s'";
		$rrdcmd .= " 'GPRINT:A:AVERAGE:ave=%.0lf %s'";
		$rrdcmd .= " 'GPRINT:A:MAX:max=%.0lf %s)' 'COMMENT:\\n'";
		$rrdcmd .= " 'LINE1:B#0000FF:Out' 'GPRINT:B:MIN:(min=%.0lf %s'";
		$rrdcmd .= " 'GPRINT:B:AVERAGE:ave=%.0lf %s'";
		$rrdcmd .= " 'GPRINT:B:MAX:max=%.0lf %s)' 'COMMENT:\\n'";
		$rrdcmd .= " 'COMMENT:Total:' 'GPRINT:C:MIN:(min=%.0lf %s'";
		$rrdcmd .= " 'GPRINT:C:AVERAGE:ave=%.0lf %s'";
		$rrdcmd .= " 'GPRINT:C:MAX:max=%.0lf %s)' 'COMMENT:\\n'";
		$w = exec($rrdcmd, $error, $rv);
		return $rv;
	} else {
		return -1;
	}
}

// Default parameterek
$mode = 0; $v = 0; $h = 0; $l = 0;

if (1==$argc) {
	$mode=4;
} else {
	for ($i=1; $i<$argc; $i++) {
		switch ($argv[$i]) {
			case 1:
				$mode=1;
			break;
			case 2:
				$mode=2;
			break;
			case 3:
				$mode=3;
			break;
			case 4:
				$mode=4;
			break;
			case '--verbose':
			case '-v':
				$v = 1;
			break;
			case '-l':
			case '--light':
				$l = 1;
			break;
			case '-h':
			case '--help':
				$h=1;
			break;
			default:
				$h=1;
			break;
		}
	}
	if ((($v==1) || ($l==1)) && ($mode == 0)) {
		$mode=4;
	}
}
 
if ($h) {
	print("Usage: $argv[0]  [-v] [-h] [--help] [1] [2] [3] [4]\n\n");
	print("\t-h, --help\tdisplay this help and exit\n");
	print("\t-v, --verbose\tVerbose mode\n");
	print("\t-l, --light\tLight mode\n");
	print("\t1\tCreate images: daily\n");
	print("\t2\tCreate images: daily and weekly\n");
	print("\t3\tCreate images: daily, weekly and monthly \n");
	print("\t4 [default]\tCreate all images.\n\n");
} else {
	$y = date("Y-m-d H:");
	$m = date("i");
	$m = $m-($m%5);
	if ($m<'10') {
		$m = "0$m";
	}
	$d = $y.$m.':00';
	$t=strtotime($d);


	if ($l) {
		$db =& DB::connect($dsn);
		if (DB::isError($db)) {
			die($db->getMessage());
		}
		$db->setFetchMode(DB_FETCHMODE_ASSOC);

		switch ($mode) {
			case 1:
				$SQL = "SELECT ip FROM rrd_now WHERE date='$d' GROUP BY ip";
				$result =& $db->query($SQL);
				if ($result->numRows()>0) {
					if ($v) {
						while ($r =& $result->fetchRow()) {
							echo 'Creating image '.$r["ip"].' : daily...';
							$ok = cgraph ('daily', $r["ip"],' -2000m', $t);
							if ($ok != '-1') {
								echo "OK\n";
							} else {
								echo " ERROR: No such file or directory.\n";
							}
						}
					} else {
						while ($r =& $result->fetchRow()) {
							cgraph ('daily', $r["ip"],' -2000m', $t);
						}
					}
				}
			break;
			case 2:
				$dp =  date("Y-m-d H:00:00",strtotime("-1 hour"));
				$SQL = "CREATE TEMPORARY TABLE tmp_table ( `ip` varchar(15) default NULL)";
				$result =& $db->query($SQL);
				$SQL = "INSERT INTO tmp_table (ip) SELECT ip FROM rrd_now GROUP BY ip";
				$result =& $db->query($SQL);
				$SQL = "INSERT INTO tmp_table (ip) SELECT ip FROM rrd_hourly WHERE date>='$dp' GROUP BY ip";
				$result =& $db->query($SQL);
				$SQL = "SELECT ip FROM tmp_table GROUP BY ip";
				$result =& $db->query($SQL);
				if ($result->numRows()>0) {
					if ($v) {
						while ($r =& $result->fetchRow()) {
							echo 'Creating image '.$r["ip"].' : daily...';
							$ok = cgraph ('daily', $r["ip"],' -2000m', $t);
							if ($ok != '-1') {
								echo 'OK';
								echo ', weekly...';
								cgraph ('weekly', $r["ip"], '-12000m', $t);
								echo "OK\n";
							} else {
								echo " ERROR: No such file or directory.\n";
							}
						}
					} else {
						while ($r = & $result->fetchRow()) {
							cgraph ('daily', $r["ip"],' -2000m', $t);
							$ok = cgraph ('daily', $r["ip"],' -2000m', $t);
							if ($ok != '-1') {
								cgraph ('weekly', $r["ip"], '-12000m', $t);
							}
						}
					}
				}
			break;
			case 3:
				$dp =  date("Y-m-d 00:00:00",strtotime("-1 days"));
				$SQL = "CREATE TEMPORARY TABLE tmp_table ( `ip` varchar(15) default NULL)";
				$result =& $db->query($SQL);
				$SQL = "INSERT INTO tmp_table (ip) SELECT ip FROM rrd_now GROUP BY ip";
				$result =& $db->query($SQL);
				$SQL = "INSERT INTO tmp_table (ip) SELECT ip FROM rrd_hourly GROUP BY ip";
				$result =& $db->query($SQL);
				$SQL = "INSERT INTO tmp_table (ip) SELECT ip FROM rrd_daily  WHERE date>='$dp' GROUP BY ip";
				$result =& $db->query($SQL);
				$SQL = "SELECT ip FROM tmp_table GROUP BY ip";
				$result =& $db->query($SQL);
				if ($result->numRows()>0) {
					if ($v) {
						while ($r =& $result->fetchRow()) {
							echo 'Creating image '.$r["ip"].' : daily...';
							$ok = cgraph ('daily', $r["ip"],' -2000m', $t);
							if ($ok != '-1') {
								echo 'OK';
								echo ', weekly...';
								cgraph ('weekly', $r["ip"], '-12000m', $t);
								echo 'OK';
								echo ', monthly...';
								cgraph ('monthly', $r["ip"], '-800h', $t);
								echo "OK\n";
							} else {
								echo " ERROR: No such file or directory.\n";
							}
						}
					} else {
						while ($r =& $result->fetchRow()) {
							$ok = cgraph ('daily', $r["ip"],' -2000m', $t);
							if ($ok != '-1') {
								cgraph ('weekly', $r["ip"], '-12000m', $t);
								cgraph ('monthly', $r["ip"], '-800h', $t);
							}
						}
					}
				}
			break;
			case 4:
				$dp =  date("Y-m-d 00:00:00",strtotime("-1 week"));
				$SQL = "CREATE TEMPORARY TABLE tmp_table ( `ip` varchar(15) default NULL)";
				$result =& $db->query($SQL);
				$SQL = "INSERT INTO tmp_table (ip) SELECT ip FROM rrd_now GROUP BY ip";
				$result =& $db->query($SQL);
				$SQL = "INSERT INTO tmp_table (ip) SELECT ip FROM rrd_hourly GROUP BY ip";
				$result =& $db->query($SQL);
				$SQL = "INSERT INTO tmp_table (ip) SELECT ip FROM rrd_daily  WHERE date>='$dp' GROUP BY ip";
				$result =& $db->query($SQL);
				$SQL = "SELECT ip FROM tmp_table GROUP BY ip";
				$result =& $db->query($SQL);
				if ($result->numRows()>0) {
					if ($v) {
						while ($r =& $result->fetchRow()) {
							echo 'Creating image '.$r["ip"].' : daily...';
							$ok = cgraph ('daily', $r["ip"],' -2000m', $t);
							if ($ok != '-1') {
								echo 'OK';
								echo ', weekly...';
								cgraph ('weekly', $r["ip"], '-12000m', $t);
								echo 'OK';
								echo ', monthly...';
								cgraph ('monthly', $r["ip"], '-800h', $t);
								echo 'OK';
								echo ', yearly...';
								cgraph ('yearly', $r["ip"], '-400d', $t);
								echo "OK\n";
							} else {
								echo " ERROR: No such file or directory.\n";
							}
						}
					} else {
						while ($r =& $result->fetchRow()) {
							$ok = cgraph ('daily', $r["ip"],' -2000m', $t);
							if ($ok != '-1') {
								cgraph ('weekly', $r["ip"], '-12000m', $t);
								cgraph ('monthly', $r["ip"], '-800h', $t);
								cgraph ('yearly', $r["ip"], '-400d', $t);
							}
						}
					}
				}
			break;
		}
		$db->disconnect();
	} else {
		if ($dir = @opendir($rrddir)) {
			switch ($mode) {
				case 1:
					if ($v) {
						while ($file = readdir($dir)) {
							if ($file != '.' && $file != '..' && !is_dir($adir.'/'.$file)) {
								$file = trim($file, '.rrd');
								echo 'Creating image '.$file.' : daily...';
								cgraph ('daily', $file,' -2000m', $t);
								echo "OK\n";
							}
						}
					} else {
						while ($file = readdir($dir)) {
							if ($file != '.' && $file != '..' && !is_dir($adir.'/'.$file)) {
								$file = trim($file, '.rrd');
								cgraph ('daily', $file,' -2000m', $t);
							}
						}
					}
				break;
				case 2:
					if ($v) {
						while ($file = readdir($dir)) {
							if ($file != '.' && $file != '..' && !is_dir($adir.'/'.$file)) {
								$file = trim($file, '.rrd');
								echo 'Creating image '.$file.' : daily...';
								cgraph ('daily', $file,' -2000m', $t);
								echo 'OK';
								echo ', weekly...';
								cgraph ('weekly', $file, '-12000m', $t);
								echo "OK\n";
							}
						}
					} else {
						while ($file = readdir($dir)) {
							if ($file != '.' && $file != '..' && !is_dir($adir.'/'.$file)) {
								$file = trim($file, '.rrd');
								cgraph ('daily', $file,' -2000m', $t);
								cgraph ('weekly', $file, '-12000m', $t);
							}
						}
					}
				break;
				case 3:
					if ($v) {
						while ($file = readdir($dir)) {
							if ($file != '.' && $file != '..' && !is_dir($adir.'/'.$file)) {
								$file = trim($file, '.rrd');
								echo 'Creating image '.$file.' : daily...';
								cgraph ('daily', $file,' -2000m', $t);
								echo 'OK';
								echo ', weekly...';
								cgraph ('weekly', $file, '-12000m', $t);
								echo 'OK';
								echo ', monthly...';
								cgraph ('monthly', $file, '-800h', $t);
								echo "OK\n";
							}
						}
					} else {
						while ($file = readdir($dir)) {
							if ($file != '.' && $file != '..' && !is_dir($adir.'/'.$file)) {
								$file = trim($file, '.rrd');
								cgraph ('daily', $file,' -2000m', $t);
								cgraph ('weekly', $file, '-12000m', $t);
								cgraph ('monthly', $file, '-800h', $t);
							}
						}
					}
				break;
				case 4:
					if ($v) {
						while ($file = readdir($dir)) {
							if ($file != '.' && $file != '..' && !is_dir($adir.'/'.$file)) {
								$file = trim($file, '.rrd');
								echo 'Creating image '.$file.' : daily...';
								cgraph ('daily', $file,' -2000m', $t);
								echo 'OK';
								echo ', weekly...';
								cgraph ('weekly', $file, '-12000m', $t);
								echo 'OK';
								echo ', monthly...';
								cgraph ('monthly', $file, '-800h', $t);
								echo 'OK';
								echo ', yearly...';
								cgraph ('yearly', $file, '-400d', $t);
								echo "OK\n";
							}
						}
					} else {
						while ($file = readdir($dir)) {
							if ($file != '.' && $file != '..' && !is_dir($adir.'/'.$file)) {
								$file = trim($file, '.rrd');
								cgraph ('daily', $file,' -2000m', $t);
								cgraph ('weekly', $file, '-12000m', $t);
								cgraph ('monthly', $file, '-800h', $t);
								cgraph ('yearly', $file, '-400d', $t);
							}
						}
					}
				break;
			}
			closedir($dir);
		}
	}
}
?>