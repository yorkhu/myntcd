<?php
require_once('DB.php');
$lock_file = '/tmp/myntc.lock';
$cfg_file = '/usr/local/etc/myntcd/myntcd.conf';

/** Read config file */
function read_cfg() {
	global $cfg_file;
	if ( !is_dir($cfg_file) ) {
		$f = @fopen($cfg_file, 'r');
		if ($f) {
			while (!feof($f)) {
				$line = fgets($f);
				$pos = strpos($line, '#');
				if ($pos !== false ) {
					$line = substr($line, 0, $pos);
				}
				if ( !(($line == '') || ($line == "\n")) ) {
					list($key, $value) = explode (' ', $line, 2);
					$config[$key] .= $value;
				}
			}
			fclose($f);
		}
		while (!is_null($key = key($config) ) ) {
			$config[$key] = trim($config[$key]);
			$config[$key] = preg_replace("/\s+/"," " ,$config[$key]);
			if ($key == 'limit') {
				$value = $config[$key];
				$config[$key] = array();
				list($config['limit']['in'], $config['limit']['out'], $config['limit']['time']) = explode (' ', $value);
				$config['limit']['in'] = $config['limit']['in']*pow(1024,3);
				$config['limit']['out'] = $config['limit']['out']*pow(1024,3);
			}
			if ($key == 'mynetwork') {
				$config[$key] = explode (' ', $config[$key]);
			}
			next($config);
		}
	} else {
		$config = 0;
	}
	return $config;
}
?>
