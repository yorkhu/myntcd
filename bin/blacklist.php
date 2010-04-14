#! /usr/bin/php
<?php
$basedir = dirname(__FILE__);
require_once($basedir.'/lib.php');

$config = read_cfg();
if ( $config ) {
	$db =& DB::connect($config['dbs']);
	if (DB::isError($db)) {
		die($db->getMessage());
	}
	$db->setFetchMode(DB_FETCHMODE_ASSOC);

	$update = 0;
	if (date('i') == '00') {
		$update = 1;
	} else {
		$d_prev = date('Y-m-d H:i:00', strtotime('-5 min'));
	
		#blacklist es whitelist modositasanak vizsgalata
		$SQL = "SHOW TABLE STATUS LIKE '%filter'";
		$result =& $db->query($SQL);
		$r =& $result->fetchRow();
		if ($d_prev <= $r['Update_time']) {
			$update = 1;
		}
	}

	if ($update) {
		#szures elavult elemeinek torlese
		$SQL = "DELETE FROM filter WHERE date < '".date('Y-m-d')."' AND date > '0000-00-00'";
		$result =& $db->query($SQL);
		
		#Szurolista elkeszitese...
		$filter_start = date("Y.m.d", strtotime('-'.$config['limit']['time'].' day'));
		$SQL = "CREATE TEMPORARY TABLE tmp_filter ( `ip` varchar(15) default '')";
		$result =& $db->query($SQL);

		if ($config['limit']['out'] > 0) {
			$SQL = "INSERT INTO tmp_filter SELECT ip FROM now WHERE date >= '".date('Y-m-d 00:00:00')."'";
			$SQL .= " GROUP BY ip HAVING SUM(out_byte) >= '".$config['limit']['out']."'";
			$result =& $db->query($SQL);

			$SQL = "INSERT INTO tmp_filter SELECT ip FROM daily WHERE date >= '".$filter_start."'";
			$SQL .= " GROUP BY ip HAVING SUM(out_byte) >= '".$config['limit']['out']."'";
			$result =& $db->query($SQL);
		}

		if ($config['limit']['in'] > 0) {
			$SQL = "INSERT INTO tmp_filter SELECT ip FROM now WHERE date >= '".date('Y-m-d 00:00:00')."'";
			$SQL .= " GROUP BY ip HAVING SUM(in_byte) >= '".$config['limit']['in']."'";
			$result =& $db->query($SQL);

			$SQL = "INSERT INTO tmp_filter SELECT ip FROM daily WHERE date >= '".$filter_start."'";
			$SQL .= " GROUP BY ip HAVING SUM(in_byte) >= '".$config['limit']['in']."'";
			$result =& $db->query($SQL);
		}

		$SQL = "DELETE FROM tmp_filter USING tmp_filter, filter WHERE tmp_filter.ip=filter.ip";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			echo $result->getMessage();
		}

		$SQL = "INSERT INTO filter SELECT ip, 1, '".date('Y-m-d 00:00:00', strtotime('+'.$config['limit']['time'].'day'))."', '' FROM tmp_filter";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			echo $result->getMessage();
		}

		$SQL = "SELECT ip FROM filter WHERE blacklist = '1'";
		$SQL .= " WHERE whitelist.ip is null GROUP BY ip";
		$result =& $db->query($SQL);
		
//		include($basedir.'/blacklist.cisco.php');
	}
	$db->disconnect();
}
?>
