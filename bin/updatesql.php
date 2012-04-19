#! /usr/bin/php
<?php
$basedir = dirname(__FILE__);
require_once($basedir.'/lib.php');

$config = read_cfg();
if ( $config ) {
	$db =& DB::connect($config['dbs']);
	if (DB::isError($db)) {
		openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
		syslog(LOG_ERR, $db->getMessage());
		closelog();
		return false;
	}
	$db->setFetchMode(DB_FETCHMODE_ASSOC);

	$SQL = "CREATE TABLE IF NOT EXISTS `ipranges` (";
	$SQL .= "`ipr_id` smallint(6) NOT NULL AUTO_INCREMENT, ";
	$SQL .= "`iprange` varchar(39) NOT NULL, ";
	$SQL .= "`mask` varchar(39) NOT NULL, ";
	$SQL .= "PRIMARY KEY (`ipr_id`))";
	$result =& $db->query($SQL);
	if (DB::isError($result)) {
		openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
		syslog(LOG_ERR, $result->getMessage());
		closelog();
		return false;
	}

	$epmty = true;
	$exist = false;
	$change = false;
	$SQL = "SELECT * FROM ipranges";
	$result =& $db->query($SQL);
	if (DB::isError($result)) {
		openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
		syslog(LOG_ERR, $result->getMessage());
		closelog();
		return false;
	}
	while ($r =& $result->fetchRow()) {
		$empty = false;
		$ipranges['ipr_id'][] = $r['ipr_id'];
		$ipranges['iprange'][] = $r['iprange'];
		$ipranges['mask'][] = $r['mask'];
	}
	if (!$empty) {
		for ($i = 0; $i < count($config['mynetwork']['iprange']); $i++) {
			for ($j = 0; $j < count($ipranges['iprange']); $j++) {
				if (inet_pton($config['mynetwork']['iprange'][$i]) == inet_pton($ipranges['iprange'][$j]) && inet_pton($config['mynetwork']['mask'][$i]) == inet_pton($ipranges['mask'][$j])) {
					$exist = true;
				}
			}
			if (!$exist) {
				$SQL = "INSERT INTO ipranges (iprange, mask) VALUES ('".$config['mynetwork']['iprange'][$i]."', '".$config['mynetwork']['mask'][$i]."')";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $result->getMessage());
					closelog();
					return false;
				}
				$change = true;
			} else {
				$exist = false;
			}
		}
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
					openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $result->getMessage());
					closelog();
					return false;
				}
				$change = true;
			} else {
				$exist = false;
			}
		}
	} else {
		for ($i = 0; $i < count($config['mynetwork']['iprange']); $i++) {
			$SQL = "INSERT INTO ipranges (iprange, mask) VALUES ('".$config['mynetwork']['iprange'][$i]."', '".$config['mynetwork']['mask'][$i]."')";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $result->getMessage());
				closelog();
				return false;
			}
		}
		for ($i = 0; $i < count($config['mynetwork6']['iprange']); $i++) {
			$SQL = "INSERT INTO ipranges (iprange, mask) VALUES ('".$config['mynetwork6']['iprange'][$i]."', '".$config['mynetwork6']['mask'][$i]."')";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $result->getMessage());
				closelog();
				return false;
			}
		}
		$change = true;
	}
	if ($change) {
		$ipranges = array();
		$SQL = "SELECT * FROM ipranges";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
			syslog(LOG_ERR, $result->getMessage());
			closelog();
			return false;
		}
		while ($r =& $result->fetchRow()) {
			$ipranges['ipr_id'][] = $r['ipr_id'];
			$ipranges['iprange'][] = $r['iprange'];
			$ipranges['mask'][] = $r['mask'];
		}
	}

	for ($i = 0; $i < count($ipranges['iprange']); $i++) {
		$ipranges['iprange'][$i] = inet_pton($ipranges['iprange'][$i]);
		$ipranges['mask'][$i] = inet_pton($ipranges['mask'][$i]);
	}

	$exist = false;
	$table = array('tmp', 'now', 'daily', 'filter');
	for ($i = 0; $i < count($table); $i++) {
		if ($table[$i] != 'filter') {
			$exist = false;
			$SQL = "SHOW COLUMNS FROM $table[$i]";
			$result =& $db->query($SQL);
			if (DB::isError($result)) {
				openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $result->getMessage());
				closelog();
				return false;
			}
			while ($r =& $result->fetchRow()) {
				if($r['Field'] == 'ipr_id') {
					$exist = true;
					break;
				}
			}
			if (!$exist) {
				$SQL = "ALTER TABLE $table[$i] ADD `ipr_id` smallint(5) unsigned NOT NULL AFTER ip";
				$result =& $db->query($SQL);
				if (DB::isError($result)) {
					openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
					syslog(LOG_ERR, $result->getMessage());
					closelog();
					return false;
				}
			}
		}
		$SQL = "ALTER TABLE $table[$i] CHANGE `ip` `ip` VARCHAR(39)";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
			syslog(LOG_ERR, $result->getMessage());
			closelog();
			return false;
		}
	}

	$table = array('now', 'daily');
	for ($i = 0; $i < count($table); $i++) {
		$SQL = "SELECT id, ip FROM $table[$i]";
		$result =& $db->query($SQL);
		if (DB::isError($result)) {
			openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
			syslog(LOG_ERR, $result->getMessage());
			closelog();
			return false;
		}
		while ($r =& $result->fetchRow()) {
			$addr = inet_pton($r['ip']);

			for ($j = 0; $j < count($ipranges['iprange']); $j++) {
				if (($addr & $ipranges['mask'][$j]) == $ipranges['iprange'][$j]) {
					$iprid = $ipranges['ipr_id'][$j];
				}
			}

			$SQL = "UPDATE $table[$i] SET ipr_id = $iprid WHERE id = ".$r['id'];
			$result2 =& $db->query($SQL);
			if (DB::isError($result2)) {
				openlog("updatesql.php", LOG_PID, LOG_LOCAL0);
				syslog(LOG_ERR, $result2->getMessage());
				closelog();
				return false;
			}
		}
	}

	$db->disconnect();
}
?>
