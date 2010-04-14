<?php
require_once('/etc/myntcd/myntcd.conf');
require_once('include/lib.php');
require_once('DB.php');

$db =& DB::connect($dsn);
if (DB::isError($db)) {
	die($db->getMessage());
}

$d_start = date('Y.m.d 00:00:00', strtotime("-$limit[time] day"));
if (isset($_GET[action])) {
	if (($_GET[action] == 'add') && (isset($_POST[addip])) && ($_POST[addip]!='')) {
		$result =  mysql_query("INSERT INTO whitelist (ip, date) VALUES('$_POST[addip]', now())");
	} elseif (($_GET[action] == 'del') && is_array($_POST[delip])) {
		while (list($k, $g) = each($_POST[delip])) {
			if ($g != '') {
				$result =  mysql_query("DELETE FROM whitelist WHERE ip='$g'");
			}
		}
	}
	header('Location: whitelist.php');
	die;
}
hp_header('Whitel List');

echo '<div class="name">Whitel List</div>'."\n";

echo '<table>'."\n";
echo '<tr><td class="ip">'."\n";

echo 'Szolgaltato gepek:<br>'."\n";
echo '<br>'."\n";
$SQL = "SELECT ip FROM whitelist WHERE date = '0000-00-00' ORDER BY ip, date";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}

if ($result->numRows()>0) {
	while ($row =& $result->fetchRow()) {
		echo $row[0].'<br>'."\n";
	}
}

echo '</td><td class="ip">'."\n";
echo '<FORM name="whitelist-del" method="post" action="whitelist.php?action=del">'."\n";
echo 'Ideiglenes gepek:<br>'."\n";
echo '<br>'."\n";
$SQL = "SELECT ip FROM whitelist WHERE date > '0000-00-00' ORDER BY ip, date";
$result =& $db->query($SQL);
if (DB::isError($result)) {
	die($result->getMessage());
}
if ($result->numRows()>0) {
	$i=0;
	while ($row =& $result->fetchRow()) {
		echo '<input type="checkbox" name="delip['.$i.']" value="'.$row[0].'"> '.$row[0]."<br>\n";
		$i++;
	}
}
echo '<br>'."\n";
echo '<input type="submit" value="Del"> <br>'."\n";
echo '</FORM>'."\n";
echo '<br>'."\n";
echo '<FORM name="whitelist-add" method="post" action="whitelist.php?action=add">'."\n";
echo 'New: <input type="text" name="addip" size="20"> <input type="submit" name="new" value="New"> <br>'."\n";

echo '</FORM>'."\n";
echo '</td></tr>'."\n";


$db->disconnect();
hp_footer();

?>