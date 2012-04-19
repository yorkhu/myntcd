<?php
require_once('DB.php');
$lock_file = '/tmp/myntc.lock';
$cfg_file = '/usr/local/etc/myntcd/myntcd.conf';
define('IPV4_BINADDRLEN', 32);
define('IPV6_BINADDRLEN', 128);
define('IPV4_BINQUADPERNETMASK', 8);
define('IPV6_BINOCTETPERNETMASK', 16);


/* Read config file */
function read_cfg() {
	global $cfg_file;
	if ( !is_dir($cfg_file) ) {
		$f = @fopen($cfg_file, 'r');
		if ($f === false) {
			openlog("lib.php", LOG_PID, LOG_LOCAL0);
			syslog(LOG_ERR, "Can't open $cfg_file file!");
			closelog();
			return false;
		}
		if ($f) {
			while (!feof($f)) {
				$line = fgets($f);
				$pos = strpos($line, '#');
				if ($pos !== false ) {
					$line = '';
				}
				if ( !(($line == '') || ($line == "\n")) ) {
					list($key, $value) = explode (' ', $line, 2);
					// @ suppress "PHP Notice:  Undefined variable:" and "PHP Notice:  Undefined index:" minor error message
					@$config[$key] .= $value;
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
				$config['limit']['in'] = $config['limit']['in'] * pow(1024, 3);
				$config['limit']['out'] = $config['limit']['out'] * pow(1024, 3);
			}
			if ($key == 'mynetwork' || $key == 'mynetwork6') {
				$config[$key] = explode (' ', $config[$key]);
			}
			next($config);
		}
		$value = $config['mynetwork'];
		$config['mynetwork'] = array();
		for ($i = 0; $i < count($value); $i++) {
			list($config['mynetwork']['iprange'][], $config['mynetwork']['mask'][]) = explode('/', $value[$i]);
			if (strlen($config['mynetwork']['mask'][$i]) <= 2) {
				$config['mynetwork']['mask'][$i] = nmbtonm(AF_INET, $config['mynetwork']['mask'][$i]);
			}
		}
		$value = $config['mynetwork6'];
		$config['mynetwork6'] = array();
		for ($i = 0; $i < count($value); $i++) {
			list($config['mynetwork6']['iprange'][], $config['mynetwork6']['mask'][]) = explode('/', $value[$i]);
			if (strlen($config['mynetwork6']['mask'][$i]) <= 2) {
				$config['mynetwork6']['mask'][$i] = nmbtonm(AF_INET6, $config['mynetwork6']['mask'][$i]);
			}
		}
	} else {
		$config = 0;
	}
	return $config;
}

/* netmaskbit to netmask */
function nmbtonm($af, $bits) {
	if($af == AF_INET) {
		$ipbinaddrlen = IPV4_BINADDRLEN;
		$pieceofnetmasklen = IPV4_BINQUADPERNETMASK;
	}
	if($af == AF_INET6) {
		$ipbinaddrlen = IPV6_BINADDRLEN;
		$pieceofnetmasklen = IPV6_BINOCTETPERNETMASK;
	}

	// a megadott ertekig egyest irunk utanna nullat
	for($i = 0; $i < $ipbinaddrlen; $i++)
	{
		if($i < $bits)
		{
			$binaddr[$i] = 1;
		}
		else
		{
			$binaddr[$i] = 0;
		}
	}

	$y = $pieceofnetmasklen - 1; // milyen hosszu a netmaszk/prefix cim egy szakasza '.' vagy ':'-ig
	$x = 0;
	$straddr = array();
	// vegigmegyunk a binaris tombunkon
	for($i = 0; $i < $ipbinaddrlen; $i++) {
		// osszeadjuk az adott hosszig a ketto hatvanyait helyiertek szerint
		$x += $binaddr[$i] << $y;

		// ha elfogynak az osszeadando szamok, egy resz vegere erunk
		if($y == 0) {
			// kitevo visszakapja erteket, a legnagyobb helyierteku szam erteket kapja meg
			$y = $pieceofnetmasklen;
			// ha IPv4
			if($af == AF_INET) {
				$temp = sprintf("%d", $x);
			}
			if($af == AF_INET6) {// ha IPv6
				$temp = sprintf("%X", $x);
			}
			$x = 0;
			if($i == ($pieceofnetmasklen - 1)) {
				// bemasoljuk az elso erteket
				$straddr = $temp;
				// elvalaszto jelet kirakjuk
				if($af == AF_INET) {
					$straddr .= ".";
				}
				if($af == AF_INET6) {
					$straddr .= ":";
				}
			} else {
				// tobbi erteket masoljuk
				$straddr .= $temp;
				// a vegen mar nem rakunk elvalaszto jelet
				if($i != ($ipbinaddrlen - 1)) {
					if($af == AF_INET) {
						$straddr .= ".";
					}
					if($af == AF_INET6) {
						$straddr .= ":";
					}
				}
			}
		}
		$y--;
	}
	return $straddr;
}
?>
