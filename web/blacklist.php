<?php
require_once('/etc/myntcd/myntcd.conf');
require_once('include/lib.php');
require_once('DB.php');

$db =& DB::connect($dsn);
if (DB::isError($db)) {
	die($db->getMessage());
}

if (isset($_GET[action])) {
	if (($_GET[action] == 'add') && (isset($_POST[addip])) && ($_POST[addip]!='')) {
		$SQL = "INSERT INTO blacklist (ip, date) VALUES('$_POST[addip]', now())";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			die($result->getMessage());
		}
	} elseif (($_GET[action] == 'del') && is_array($_POST[delip])) {
		while (list($k, $g) = each($_POST[delip])) {
			if ($g != '') {
				$SQL = "DELETE FROM blacklist WHERE ip='$g'";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					die($result->getMessage());
				}
			}
		}
	}
	header('Location: blacklist.php');
	die;
}

hp_header('Black List');

$d_start = date('Y.m.d 00:00:00', strtotime("-$limit[time] day"));

echo '<div class="name">Az elmult '.$limit[time].' napban a kovetkezok leptek tul a forgalomi limitet:</div>'."\n";

$SQL = "CREATE TEMPORARY TABLE tmp_table AS";
$SQL .= " SELECT ip, SUM(out_byte) as out_byte FROM rrd_now GROUP BY ip, type";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}

$SQL = "INSERT INTO tmp_table SELECT ip, SUM(out_byte) as out_byte FROM rrd_hourly GROUP BY ip, type";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}

echo '<table>'."\n";
echo '<tr><td class="ip">'."\n";

$d_start = date('Y.m.d 00:00:00', strtotime("-$limit[time] day"));

$SQL = "CREATE TEMPORARY TABLE ip_list AS";
$SQL .= " SELECT ip FROM rrd_daily";
$SQL .= " WHERE date>='$d_start' AND out_byte>='".$limit["out_byte"]."' GROUP BY ip";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}

$SQL = "INSERT INTO ip_list SELECT ip FROM tmp_table";
$SQL .= " GROUP BY ip HAVING SUM(out_byte) >= '".$limit["out_byte"]."'";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}

$SQL = "INSERT INTO ip_list SELECT ip FROM blacklist";
$SQL .= " WHERE date>='$d_start'";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}

$SQL = "SELECT ip_list.ip FROM ip_list LEFT JOIN whitelist ON (ip_list.ip = whitelist.ip)";
$SQL .= " WHERE whitelist.ip is null GROUP BY ip";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}

if ($result->numRows()>0) {
	while ($row =& $result->fetchRow()) {
		echo "<a href=\"ip_stat.php?ip=$row[0]\">$row[0]</a><br>\n";
	}
}

echo '</td><td class="ip">'."\n";

echo '<FORM name="blacklist-del" method="post" action="blacklist.php?action=del">'."\n";
echo 'Felvett gepek:<br>'."\n";
echo '<br>'."\n";
$SQL = "SELECT ip FROM blacklist ORDER BY ip, date";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}
if ($result->numRows()>0) {
	$i=0;
	while ($row =& $result->fetchRow()) {
		echo '<input type="checkbox" name="delip['.$i.']" value="'.$row[0].'"> '.$row[0].'<br>'."\n";
		$i++;
	}
}
echo '<br>'."\n";
echo '<input type="submit" value="Del"> <br>'."\n";
echo '</FORM>'."\n";
echo '<br>'."\n";
echo '<FORM name="blacklist-add" method="post" action="blacklist.php?action=add">'."\n";
echo 'New: <input type="text" name="addip" size="20"> <input type="submit" name="new" value="New"> <br>'."\n";

echo '</FORM>'."\n";
echo '</td></tr>'."\n";
echo '</table>'."\n";
echo '<br><br><br><br>Date: '.date("Y.m.d")."\n";

$db->disconnect();
hp_footer();

?>