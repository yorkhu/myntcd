#! /usr/bin/php4
<?php
require_once('DB.php');
require_once '/etc/myntcd/myntcd.conf';

if (!is_dir($web_dir)) {
	if(!file_exists($web_dir)) {
		mkdir($web_dir);
	}
}

$db =& DB::connect($dsn);
if (DB::isError($db)) {
	die($db->getMessage());
}
$db->setFetchMode(DB_FETCHMODE_ASSOC);

function hp_header($f, $title) {
	fwrite($f, "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n");
	fwrite($f, "<html>\n");
	fwrite($f, "<head>\n");
	fwrite($f, "	<title>$title</title>\n");
	fwrite($f, "	<meta name=\"pragma\" content=\"no-cache\">\n");
	fwrite($f, "	<meta name=\"cache-content\" content=\"no-cache\">\n");
	fwrite($f, "	<meta http-equiv=\"pragma\" content=\"no-cache\">\n");
	fwrite($f, "	<meta http-equiv=\"cache-content\" content=\"no-cache\">\n");
	fwrite($f, "	<link rel=stylesheet href=\"./myntcd.css\" type=\"text/css\">\n");

	fwrite($f, "</head>\n");
	fwrite($f, "<body leftmargin='0' topmargin=\"0\" rightmargin=\"0\" bottommargin=\"0\" marginwidth=\"0\" marginheight=\"0\">\n");
}

function hp_footer($f) {
	fwrite($f, "</body>\n");
	fwrite($f, "</html>\n");
}

function start_table($f, $name, $param, $c1, $c2, $c3, $c4, $c5) {
	fwrite($f, "<table $param border='2'>\n");
	if ($name != "") {
		fwrite($f, "<tr>\n");
		fwrite($f, "	<td colspan= '5'>$name</td>\n");
		fwrite($f, "</tr>\n");
	}
	fwrite($f, "<tr>\n");
	fwrite($f, "	<td class=\"ip2\">$c1</td>\n");
	fwrite($f, "	<td class=\"data\">$c2</td>\n");
	fwrite($f, "	<td class=\"data\">$c3</td>\n");
	fwrite($f, "	<td class=\"data\">$c4</td>\n");
	fwrite($f, "	<td class=\"percent\">$c5</td>\n");
	fwrite($f, "</tr>\n");
}

function end_table($f) {
	fwrite($f, "	</table>\n");
}

function new_search($f, $hp) {
	$i_max = date("Y");
	$M = date("m");
	$D = date("d");
	fwrite($f, "<div class=\"src\">\n");
	fwrite($f, "		<FORM name='itstat' method='post' action='$hp'>\n");
	fwrite($f, "		Traffic Data:\n");
	fwrite($f, "		<SELECT class=\"year\" name='start_y'>\n");
	for ($i=2004 ; $i<=$i_max ; $i++) {
		fwrite($f, "		<OPTION value='$i' SELECTED>$i</OPTION>\n");
	}
	fwrite($f, "		</SELECT>\n");
	fwrite($f, "		<SELECT name='start_m'>\n");
	for ($i=1 ; $i<=12 ; $i++) {
		if ($i<10) {
			if ($M!=$i) {
				fwrite($f, "		<OPTION value='0$i'>0$i</OPTION>\n");
			} else {
				fwrite($f, "		<OPTION value='0$i' SELECTED>0$i</OPTION>\n");
			}
		} else {
			if ($M!=$i) {
				fwrite($f, "		<OPTION value='$i'>$i</OPTION>\n");
			} else {
				fwrite($f, "		<OPTION value='$i' SELECTED>$i</OPTION>\n");
			}
		}
	}
	fwrite($f, "		</SELECT>\n");
	fwrite($f, "		<SELECT name='start_d'>\n");
	for ($i=1 ; $i<=31 ; $i++) {
		if ($i<10) {
			if ($D!=$i) {
				fwrite($f, "		<OPTION value='0$i'>0$i</OPTION>\n");
			} else {
				fwrite($f, "		<OPTION value='0$i' SELECTED>0$i</OPTION>\n");
			}
		} else {
			if ($D!=$i) {
				fwrite($f, "		<OPTION value='$i'>$i</OPTION>\n");
			} else {
				fwrite($f, "		<OPTION value='$i' SELECTED>$i</OPTION>\n");
			}
		}
	}
	fwrite($f, "		</SELECT>\n");
	
	fwrite($f, " - ");
	
	fwrite($f, "		<SELECT class=\"year\" name='end_y'>\n");
	for ($i=2004 ; $i<=$i_max ; $i++) {
		fwrite($f, "		<OPTION value='$i' SELECTED>$i</OPTION>\n");
	}
	fwrite($f, "		</SELECT>\n");
	fwrite($f, "		<SELECT name='end_m'>\n");
	for ($i=1 ; $i<=12 ; $i++) {
		if ($i<10) {
			if ($M!=$i) {
				fwrite($f, "		<OPTION value='0$i'>0$i</OPTION>\n");
			} else {
				fwrite($f, "		<OPTION value='0$i' SELECTED>0$i</OPTION>\n");
			}
		} else {
			if ($M!=$i) {
				fwrite($f, "		<OPTION value='$i'>$i</OPTION>\n");
			} else {
				fwrite($f, "		<OPTION value='$i' SELECTED>$i</OPTION>\n");
			}
		}
	}
	fwrite($f, "		</SELECT>\n");
	fwrite($f, "		<SELECT name='end_d'>\n");
	for ($i=1 ; $i<=31 ; $i++) {
		if ($i<10) {
			if ($D!=$i) {
				fwrite($f, "		<OPTION value='0$i'>0$i</OPTION>\n");
			} else {
				fwrite($f, "		<OPTION value='0$i' SELECTED>0$i</OPTION>\n");
			}
		} else {
			if ($D!=$i) {
				fwrite($f, "		<OPTION value='$i'>$i</OPTION>\n");
			} else {
				fwrite($f, "		<OPTION value='$i' SELECTED>$i</OPTION>\n");
			}
		}
	}
	fwrite($f, "		</SELECT>\n");
	fwrite($f, "		 <input type='submit' value='Find'>\n");
	fwrite($f, "		</FORM></td>\n");
	fwrite($f, "	</div>\n");
}

function new_ip_search($f, $ip) {
	fwrite($f, "<div class=\"src\">\n");
	fwrite($f, "		<FORM name='ipstat' method='get' action='ip_stat.php'>\n");
	fwrite($f, "		IP:\n");
	fwrite($f, "		<SELECT class=\"ip\" name='ip'>\n");
	for ($i=1 ; $i<=255 ; $i++) {
		fwrite($f, "		<OPTION value='$ip.$i'>$ip.$i</OPTION>\n");
	}
	fwrite($f, "		</SELECT>\n");
	fwrite($f, "		 <input type='submit' value='GO!'>\n");
	fwrite($f, "		</FORM>\n");
	fwrite($f, "	</div>\n");
}

function new_line($f, $ip, $total, $in, $out) {
	fwrite($f, "	<tr>\n");
	fwrite($f, "		<td class=\"ip\">$ip</td>\n");
	fwrite($f, "		<td class=\"d-ok\">".number_format(round($total/1024/1024,0), 0, ',', ' ')." MB</td>\n");
	fwrite($f, "		<td class=\"d-ok\">".number_format(round($in/1024/1024,0), 0, ',', ' ')." MB</td>\n");
	if ($out > $in) {
		fwrite($f, "		<td class=\"d-bad\">".number_format(round($out/1024/1024,0), 0, ',', ' ')." MB</td>\n");
		if ($total != 0) {
			fwrite($f, "		<td class=\"d-bad\">". (round($out/$total,2)*100) ."%</td>\n");
		} else {
			fwrite($f, "		<tdclass=\"d-ok\"><BR></td>\n");
		}
	} else {
		fwrite($f, "		<td class=\"d-ok\">".number_format(round($out/1024/1024,0), 0, ',', ' ')." MB</td>\n");
		if ($total != 0) {
			fwrite($f, "		<td class=\"d-ok\">". (round($out/$total,2)*100) ."%</td>\n");
		} else {
			fwrite($f, "		<td class=\"d-ok\"><BR></td>\n");
		}
	}
	fwrite($f, "	</tr>\n");
}

#$f = fopen("$web_dir$web_index_file", "w+");
$f = fopen($web_dir.'/'.$web_index_tmp_file, 'w+');

hp_header($f, "");

$m = date("i");
$m = $m-($m%5);
if ($m<'10') {
	$m = '0'.$m;
}
$d_end  = date("H:")."$m:00";

$SQL = "CREATE TEMPORARY TABLE tmp_table ( `ip` varchar(15) default NULL,";
$SQL .= " `in_byte` bigint(20) unsigned NOT NULL default '0',";
$SQL .= " `out_byte` bigint(20) unsigned NOT NULL default '0')";
$result =& $db->query($SQL);

#ORA FORGALMA
fwrite($f, '		<div class="name">TRAFFIC DATA</div>'."\n");

#Black list
fwrite($f, '		<div class="src"><a href="blacklist.php">Black List</a> / <a href="whitelist.php">White List</a></div>'."\n");

new_search($f, "itstat.php");
if ($m == "00") {
	$d_start = date("Y.m.d H:00:00", strtotime("-1 hour"));
	fwrite($f, '		<div class="date">'.$d_start.' - '.$d_end.'</div>'."\n");
	fwrite($f, '<div class="block">'."\n");
	$d_start = date("Y.m.d H:00:00");
	start_table($f, 'IP Ranges:', 'class="left"', '<BR>', 'Total', 'IN', 'OUT', 'OUT%');

	$SQL = "SELECT SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM rrd_now";
	$result_now =& $db->query($SQL);
	$r_now =& $result_now->fetchRow();
	
	$SQL = "SELECT SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM rrd_hourly WHERE date>='$d_start'";
	$result_day =& $db->query($SQL);
	$r_day = $result_day->fetchRow();
	
	new_line($f, 'Total:', $r_now["bsum"]+$r_day["bsum"], $r_now["in_byte"]+$r_day["in_byte"], $r_now["out_byte"]+$r_day["out_byte"]);

	$SQL = "DELETE FROM tmp_table";
	$result_tmp =& $db->query($SQL);

	$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),";
	$SQL .= " SUM(in_byte), SUM(out_byte)";
	$SQL .= " FROM rrd_now GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
	$result =& $db->query($SQL);

	$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),SUM(in_byte),";
	$SQL .= " SUM(out_byte)";
	$SQL .= " FROM rrd_hourly WHERE date>='$d_start' GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
	$result =& $db->query($SQL);
	
	$SQL = "SELECT ip ,SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM tmp_table GROUP BY ip";
	$result =& $db->query($SQL);
	
	if ($result->numRows()>0) {
		while ($r =& $result->fetchRow()) {
			new_line($f, '<a href="ip_h_'.$r["ip"].'.html">'.$r["ip"].'.0</a>', $r["bsum"], $r["in_byte"], $r["out_byte"]);
			$SQL = "DELETE FROM tmp_table";
			$result_tmp =& $db->query($SQL);
			$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM rrd_now WHERE ip LIKE '".$r["ip"]."%' GROUP BY ip";
			$result_ip =& $db->query($SQL);
			$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
			$SQL .= " FROM rrd_hourly WHERE ip LIKE '$".$r["ip"]."%'  AND date>='$d_start'GROUP BY ip";
			$result_ip =& $db->query($SQL);
			$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
			$SQL .= " FROM tmp_table GROUP BY ip ORDER BY bsum DESC";
			$result_ip =& $db->query($SQL);
			if ($result_ip->numRows()>0) {
				$f_ip = fopen($web_dir.'ip_h_'.$r["ip"].'.html', "w+");
				hp_header($f_ip, "");
				fwrite($f_ip, '<div class="name">'.$r["ip"].'.0</div>'."\n");
				fwrite($f_ip, '<div class="date">'.$d_start.' - '.$d_end.'</div>'."\n");
				new_search($f_ip, 'itstat.php?ip='.$r["ip"]);
				new_ip_search($f_ip, $r["ip"]);
				start_table($f_ip, '', '', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
				while ($r_ip =& $result_ip->fetchRow()) {
					new_line($f_ip, '<a href="ip_stat.php?ip='.$r_ip["ip"].'">'.$r_ip["ip"].'</a>', $r_ip["bsum"], $r_ip["in_byte"], $r_ip["out_byte"]);
				}
				end_table($f_ip);
				hp_footer($f_ip);
				fflush($f_ip);
				fclose($f_ip);
			}
		}
	}
	end_table($f);
	$SQL = "DELETE FROM tmp_table";
	$result_tmp =& $db->query($SQL);

	start_table($f, '<a href="top.php">TOP 20.</a>', 'class="right"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
	
	$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
	$SQL .= " FROM rrd_now GROUP BY ip";
	$result =& $db->query($SQL);
	$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
	$SQL .= " FROM rrd_hourly WHERE date>='$d_start' GROUP BY ip";
	$result =& $db->query($SQL);
	$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
	$SQL .= " FROM tmp_table GROUP BY ip ORDER BY bsum DESC";
	$result =& $db->limitQuery($SQL, 0, 20);
	if ($result->numRows()>0) {
		while ($r =& $result->fetchRow()) {
			new_line($f, '<a href="ip_stat.php?ip='.$r["ip"].'">'.$r["ip"].'</a>', $r["bsum"], $r["in_byte"], $r["out_byte"]);
		}
	}
} else {
	$d_start = date("Y.m.d H:00:00");
	fwrite($f, '		<div class="date">'.$d_start.' - '.$d_end.'</div>'."\n");
	fwrite($f, '<div class="block">'."\n");
	$d_start = date("Y.m.d H:00:00");
	start_table($f, 'IP Ranges:', 'class="left"', '<BR>', 'Total', 'IN', 'OUT', 'OUT%');
	$SQL = "SELECT SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM rrd_now";
	$result =& $db->query($SQL);
	$r =& $result->fetchRow();
	new_line($f, 'Total:', $r["bsum"], $r["in_byte"], $r["out_byte"]);
	
	$SQL = "SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))) as ip,";
	$SQL .= " SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
	$SQL .= " FROM rrd_now GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
	$result =& $db->query($SQL);
	if ($result->numRows()>0) {
		while ($r =& $result->fetchRow()) {
			new_line($f, '<a href="ip_h_'.$r["ip"].'.html">'.$r["ip"].'.0</a>', $r["bsum"], $r["in_byte"], $r["out_byte"]);

			$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
			$SQL .= " FROM rrd_now WHERE ip LIKE '".$r["ip"]."%' GROUP BY ip ORDER BY bsum DESC";
			$result_ip =& $db->query($SQL);
			if ($result_ip->numRows()>0) {
				$f_ip = fopen($web_dir.'ip_h_'.$r["ip"].'.html', "w+");
				hp_header($f_ip, "");
				fwrite($f_ip, '<div class="name">'.$r["ip"].'.0</div>'."\n");
				fwrite($f_ip, '<div class="date">'.$d_start.' - '.$d_end.'</div>'."\n");
				new_search($f_ip, 'itstat.php?ip='.$r["ip"]);
				new_ip_search($f_ip, $r["ip"]);
				start_table($f_ip, '', '', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
				while ($r_ip =& $result_ip->fetchRow()) {
					new_line($f_ip, '<a href="ip_stat.php?ip='.$r_ip["ip"].'">'.$r_ip["ip"].'</a>', $r_ip["bsum"], $r_ip["in_byte"], $r_ip["out_byte"]);
				}
				end_table($f_ip);
				hp_footer($f_ip);
				fflush($f_ip);
				fclose($f_ip);
			}
		}
	}
	end_table($f);

	start_table($f, '<a href="top.php">TOP 20.</a>', 'class="right"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');

	$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
	$SQL .= " FROM rrd_now GROUP BY ip ORDER BY bsum DESC";
	$result =& $db->limitQuery($SQL, 0, 20);
	if ($result->numRows()>0) {
		while ($r =& $result->fetchRow()) {
			new_line($f, '<a href="ip_stat.php?ip='.$r["ip"].'">'.$r["ip"].'</a>', $r["bsum"], $r["in_byte"], $r["out_byte"]);
		}
	}
}
end_table($f);
fwrite($f, '<div class="clear"></div>'."\n");
fwrite($f, '</div>'."\n");

#NAP FORGALMA
$d_start = date("Y.m.d 00:00:00");
fwrite($f, '		<div class="date">'.$d_start.' - '.$d_end.'</div>'."\n");
fwrite($f, '<div class="block">'."\n");
start_table($f, 'IP Ranges:', 'class="left"', '<BR>', 'Total', 'IN', 'OUT', 'OUT%');

$SQL = "SELECT SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM rrd_now";
$result_now =& $db->query($SQL);
$r_now  =& $result_now->fetchRow();

$SQL = "SELECT SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM rrd_hourly";
$result_day =& $db->query($SQL);
$r_day  =& $result_day->fetchRow();

new_line($f, 'Total:', $r_now["bsum"]+$r_day["bsum"], $r_now["in_byte"]+$r_day["in_byte"], $r_now["out_byte"]+$r_day["out_byte"]);

$SQL = "DELETE FROM tmp_table";
$result_tmp =& $db->query($SQL);

$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),";
$SQL .= " SUM(in_byte), SUM(out_byte)";
$SQL .= " FROM rrd_now GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
$result =& $db->query($SQL);

$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),";
$SQL .= " SUM(in_byte), SUM(out_byte)";
$SQL .= " FROM rrd_hourly GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
$result =& $db->query($SQL);

$SQL = "SELECT ip ,SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
$SQL .= " FROM tmp_table GROUP BY ip";
$result =& $db->query($SQL);

if ($result->numRows()>0) {
	while ($r =& $result->fetchRow()) {
		new_line($f, '<a href="ip_d_'.$r["ip"].'.html">'.$r["ip"].'.0</a>', $r["bsum"], $r["in_byte"], $r["out_byte"]);
		$SQL = "DELETE FROM tmp_table";
		$result_tmp =& $db->query($SQL);
		$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_now WHERE ip LIKE '".$r["ip"]."%' GROUP BY ip";
		$result_ip =& $db->query($SQL);

		$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_hourly WHERE ip LIKE '".$r["ip"]."%' GROUP BY ip";
		$result_ip =& $db->query($SQL);

		$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
		$SQL .= " FROM tmp_table GROUP BY ip ORDER BY bsum DESC";
		$result_ip =& $db->query($SQL);
		if ($result_ip->numRows()>0) {
			$f_ip = fopen($web_dir.'ip_d_'.$r["ip"].'.html', "w+");
			hp_header($f_ip, "");
			fwrite($f_ip, '<div class="name">'.$r["ip"].'.0</div>'."\n");
			fwrite($f_ip, '<div class="date">'.$d_start.' - '.$d_end.'</div>'."\n");
			new_search($f_ip, "itstat.php?ip=".$r["ip"]);
			new_ip_search($f_ip, $r["ip"]);
			start_table($f_ip, '', '', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
			while ($r_ip =& $result_ip->fetchRow()) {
				new_line($f_ip, '<a href="ip_stat.php?ip='.$r_ip["ip"].'">'.$r_ip["ip"].'</a>', $r_ip["bsum"], $r_ip["in_byte"], $r_ip["out_byte"]);
			}
			end_table($f_ip);
			hp_footer($f_ip);
			fflush($f_ip);
			fclose($f_ip);
		}
	}
}
$SQL = "DELETE FROM tmp_table";
$result_tmp =& $db->query($SQL);
end_table($f);

start_table($f, '<a href="top.php">TOP 20.</a>', 'class="right"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');

$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte) FROM rrd_now GROUP BY ip";
$result =& $db->query($SQL);
$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte) FROM rrd_hourly GROUP BY ip";
$result =& $db->query($SQL);
$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
$SQL .= " FROM tmp_table GROUP BY ip ORDER BY bsum DESC";
$result =& $db->limitQuery($SQL, 0, 20);

if ($result->numRows()>0) {
	while ($r =& $result->fetchRow()) {
		new_line($f, '<a href="ip_stat.php?ip='.$r["ip"].'">'.$r["ip"].'</a>', $r["bsum"], $r["in_byte"], $r["out_byte"]);
	}
}
$SQL = "DELETE FROM tmp_table";
$result_tmp =& $db->query($SQL);
end_table($f);
fwrite($f, '<div class="clear"></div>'."\n");
fwrite($f, '</div>'."\n");

#HONAP FORGALMA
$d_start = date("Y.m.01 00:00:00");
$d_db = date("Y-m-01 00:00:00");
$d_end  = date("Y.m.d H:")."$m:00";

fwrite($f, '		<div class="date">'.$d_start.' - '.$d_end.'</div>'."\n");
fwrite($f, '<div class="block">'."\n");
start_table($f, 'IP Ranges:', 'class="left"', '<BR>', 'Total', 'IN', 'OUT', 'OUT%');

$SQL = "SELECT SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM rrd_now";
$result_now =& $db->query($SQL);
$r_now  =& $result_now->fetchRow();

$SQL = "SELECT SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM rrd_hourly";
$result_day =& $db->query($SQL);
$r_day  =& $result_day->fetchRow();

$SQL = "SELECT SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum FROM rrd_daily WHERE date>'$d_db'";
$result_mount =& $db->query($SQL);
$r_mount  =& $result_mount->fetchRow();
new_line($f, 'Total:', $r_now["bsum"]+$r_day["bsum"]+$r_mount["bsum"], $r_now["in_byte"]+$r_day["in_byte"]+$r_mount["in_byte"], $r_now["out_byte"]+$r_day["out_byte"]+$r_mount["out_byte"]);

$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),";
$SQL .= " SUM(in_byte), SUM(out_byte)";
$SQL .= " FROM rrd_now GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
$result =& $db->query($SQL);

$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),";
$SQL .= " SUM(in_byte), SUM(out_byte)";
$SQL .= " FROM rrd_hourly GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
$result =& $db->query($SQL);

$SQL = "INSERT INTO tmp_table SELECT substring(ip,1,length(ip)-locate('.',reverse(ip))),";
$SQL .= " SUM(in_byte), SUM(out_byte)";
$SQL .= " FROM rrd_daily  WHERE date>'$d_db'";
$SQL .= " GROUP BY substring(ip,1,length(ip)-locate('.',reverse(ip)))";
$result =& $db->query($SQL);

$SQL = "SELECT ip ,SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
$SQL .= " FROM tmp_table GROUP BY ip";
$result =& $db->query($SQL);

if ($result->numRows()>0) {
	while ($r =& $result->fetchRow()) {
		new_line($f, '<a href="ip_m_'.$r["ip"].'.html">'.$r["ip"].'.0</a>', $r["bsum"], $r["in_byte"], $r["out_byte"]);
		$SQL = "DELETE FROM tmp_table";
		$result_tmp =& $db->query($SQL);

		$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_now WHERE ip LIKE '".$r["ip"]."%' GROUP BY ip";
		$result_ip =& $db->query($SQL);

		$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_hourly WHERE ip LIKE '".$r["ip"]."%' GROUP BY ip";
		$result_ip =& $db->query($SQL);

		$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
		$SQL .= " FROM rrd_daily WHERE date>'$d_db' AND ip LIKE '".$r["ip"]."%' GROUP BY ip";
		$result_ip =& $db->query($SQL);

		$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
		$SQL .= " FROM tmp_table GROUP BY ip ORDER BY bsum DESC";
		$result_ip =& $db->query($SQL);

		if ($result_ip->numRows()>0) {
			$f_ip = fopen($web_dir.'ip_m_'.$r["ip"].'.html', "w+");
			hp_header($f_ip, "");
			fwrite($f_ip, '<div class="name">'.$r["ip"].'.0</div>'."\n");
			fwrite($f_ip, '<div class="date">'.$d_start.' - '.$d_end.'</div>'."\n");
			new_search($f_ip, 'itstat.php?ip='.$r["ip"]);
			new_ip_search($f_ip, $r["ip"]);
			start_table($f_ip, '', '', 'IP', 'Total', 'IN', 'OUT', 'OUT%');
			while ($r_ip =& $result_ip->fetchRow()) {
				new_line($f_ip, '<a href="ip_stat.php?ip='.$r_ip["ip"].'">'.$r_ip["ip"].'</a>', $r_ip["bsum"], $r_ip["in_byte"], $r_ip["out_byte"]);
			}
			end_table($f_ip);
			hp_footer($f_ip);
			fflush($f_ip);
			fclose($f_ip);
		}
	}
}
end_table($f);
$SQL = "DELETE FROM tmp_table";
$result_tmp =& $db->query($SQL);

start_table($f, '<a href="top.php">TOP 20.</a>', 'class="right"', 'IP', 'Total', 'IN', 'OUT', 'OUT%');

$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte) FROM rrd_now GROUP BY ip";
$result =& $db->query($SQL);

$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte) FROM rrd_hourly GROUP BY ip";
$result =& $db->query($SQL);

$SQL = "INSERT INTO tmp_table SELECT ip, SUM(in_byte), SUM(out_byte)";
$SQL .= " FROM rrd_daily WHERE date>'$d_db' GROUP BY ip";
$result =& $db->query($SQL);
$SQL = "SELECT ip, SUM(in_byte) as in_byte, SUM(out_byte) as out_byte, SUM(in_byte)+SUM(out_byte) as bsum";
$SQL .= " FROM tmp_table GROUP BY ip ORDER BY bsum DESC";
$result =& $db->limitQuery($SQL, 0, 20);

if ($result->numRows()>0) {
	while ($r =& $result->fetchRow()) {
		new_line($f, '<a href="ip_stat.php?ip='.$r["ip"].'">'.$r["ip"].'</a>', $r["bsum"], $r["in_byte"], $r["out_byte"]);
	}
}
$SQL = "DELETE FROM tmp_table";
$result_tmp =& $db->query($SQL);
end_table($f);
fwrite($f, '<div class="clear"></div>'."\n");
fwrite($f, '</div>'."\n");

hp_footer($f);

fflush($f);
fclose($f);

if(file_exists($web_dir.'/'.$web_index_tmp_file)) {
	rename($web_dir.'/'.$web_index_tmp_file, $web_dir.'/'.$web_index_file);
}
$db->disconnect();
?>