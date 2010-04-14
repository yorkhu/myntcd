<?php
if (count(debug_backtrace()) == "0") {
	header('Location: index.html');
	die;
}

function hp_header($title) 
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><? echo $title; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-2">
<META HTTP-EQUIV="Pragma" CONTENT="no-cache">
<link rel=stylesheet href="./myntcd.css" type="text/css">
</head>
<body>
<?	
}

function hp_footer() 
{
?>
</body>
</html>
<?	
}

function start_ip_table($c1, $c2, $c3, $c4, $c5, $c6, $c7, $c8) {
?>
<table border='2'>
<tr>
	<td class="percent"><? echo $c1; ?></td>
	<td class="ip2"><? echo $c2; ?></td>
	<td class="ip2"><? echo $c3; ?></td>
	<td class="ip2"><? echo $c4; ?></td>
	<td class="data"><? echo $c5; ?></td>
	<td class="data"><? echo $c6; ?></td>
	<td class="data"><? echo $c7; ?></td>
	<td class="percent"><? echo $c8; ?></td>
</tr>
<?
}

function end_table() 
{
?>
</table>
<?
}

function start_table($name, $param, $c1, $c2, $c3, $c4, $c5) {
	echo "<table $param border='2'>\n";
	if ($name != "") {
		echo "<tr>\n";
		echo "	<td colspan= '5'>$name</td>\n";
		echo "</tr>\n";
	}
	echo "<tr>\n";
	echo "	<td class=\"ip2\">$c1</td>\n";
	echo "	<td class=\"data\">$c2</td>\n";
	echo "	<td class=\"data\">$c3</td>\n";
	echo "	<td class=\"data\">$c4</td>\n";
	echo "	<td class=\"percent\">$c5</td>\n";
	echo "</tr>\n";
}

function new_ip_line($type, $inp, $outp, $inb, $outb, $tp, $tb) {
	switch ($type) {
		case "-1":
			#OTHER
			$type = "Other";
		break;
		case "1":
			#ICMP
			$type = "ICMP";
		break;
		case "6":
			#TCP
			$type = "TCP";
		break;
		case "17":
			#UDP
			$type = "UDP";
		break;
	}
	echo "	<tr>\n";
	echo "		<td class=\"ip\">$type</td>\n";
	echo "		<td class=\"d-ok\">".number_format($inp, 0, ',', ' ')." db</td>\n";
	echo "		<td class=\"d-ok\">".number_format($outp, 0, ',', ' ')." db</td>\n";
	echo "		<td class=\"d-ok\">".number_format($tp, 0, ',', ' ')." db</td>\n";
	echo "		<td class=\"d-ok\">".number_format(round($inb/1024/1024,0), 0, ',', ' ')." MB</td>\n";
	if ($outb>$inb) {
		echo "		<td class=\"d-bad\">".number_format(round($outb/1024/1024,0), 0, ',', ' ')." MB</td>\n";
	} else {
		echo "		<td class=\"d-ok\">".number_format(round($outb/1024/1024,0), 0, ',', ' ')." MB</td>\n";
	}
	echo "		<td class=\"d-ok\">".number_format(round($tb/1024/1024,0), 0, ',', ' ')." MB</td>\n";
	if ($tb!=0) {
		if ($outb>$inb) {
			echo "		<td class=\"d-bad\">". (round($outb/$tb,2)*100) ."%</td>\n";
		} else {
			echo "		<td class=\"d-ok\">". (round($outb/$tb,2)*100) ."%</td>\n";
		}
	} else {
		echo "		<td><BR></td>\n";
	}
	echo "	</tr>\n";
}

function new_line($ip, $total, $in, $out) {
	echo "<tr>\n";
	echo "	<td class=\"ip\">$ip</td>\n";
	echo "	<td class=\"d-ok\">".number_format(round($total/1024/1024,0), 0, ',', ' ')." MB</td>\n";
	echo "	<td class=\"d-ok\">".number_format(round($in/1024/1024,0), 0, ',', ' ')." MB</td>\n";
	
	if ($out>$in) {
		echo "		<td class=\"d-bad\">".number_format(round($out/1024/1024,0), 0, ',', ' ')." MB</td>\n";
		if ($total != 0) {
			echo "	<td class=\"d-bad\">". (round($out/$total,2)*100) ."%</td>\n";
		} else {
			echo "	<td class=\"d-ok\"><BR></td>\n";
		}
	} else {
		echo "	<td class=\"d-ok\">".number_format(round($out/1024/1024,0), 0, ',', ' ')." MB</td>\n";
		if ($total != 0) {
			echo "	<td class=\"d-ok\">". (round($out/$total,2)*100) ."%</td>\n";
		} else {
			echo "	<td class=\"d-ok\"><BR></td>\n";
		}
	}
	echo "</tr>\n";
}

$start_y = $start_m = $start_d = $end_y = $end_m = $end_d = "";
function new_search($hp) {
	global $start_y, $start_m, $start_d, $end_y, $end_m, $end_d;
echo "<div class=\"src\">\n";
	if (($start_y!="") && ($start_m!="") && ($start_d!="")) {
		$i_max = $start_y;
		$M = $start_m;
		$D = $start_d;
	} else {
		$i_max = date("Y");
		$M = date("m");
		$D = date("d");
	}
	echo "	<FORM name='src' method='post' action='$hp'>\n";
	echo "	Traffic Data:\n";
	
	echo "	<SELECT class=\"year\" name='start_y'>\n";
	for ($i=2004 ; $i<=$i_max ; $i++) {
		echo "	<OPTION value='$i' SELECTED>$i</OPTION>\n";
	}
	echo "	</SELECT>\n";
	echo "	<SELECT name='start_m'>\n";
	for ($i=1 ; $i<=12 ; $i++) {
		if ($i<10) {
			if ($M!=$i) {
				echo "	<OPTION value='0$i'>0$i</OPTION>\n";
			} else {
				echo "	<OPTION value='0$i' SELECTED>0$i</OPTION>\n";
			}
		} else {
			if ($M!=$i) {
				echo "	<OPTION value='$i'>$i</OPTION>\n";
			} else {
				echo "	<OPTION value='$i' SELECTED>$i</OPTION>\n";
			}
		}
	}
	echo "	</SELECT>\n";
	echo "	<SELECT name='start_d'>\n";
	for ($i=1 ; $i<=31 ; $i++) {
		if ($i<10) {
			if ($D!=$i) {
				echo "	<OPTION value='0$i'>0$i</OPTION>\n";
			} else {
				echo "	<OPTION value='0$i' SELECTED>0$i</OPTION>\n";
			}
		} else {
			if ($D!=$i) {
				echo "	<OPTION value='$i'>$i</OPTION>\n";
			} else {
				echo "	<OPTION value='$i' SELECTED>$i</OPTION>\n";
			}
		}
	}
	echo "	</SELECT>\n";
	
	echo " - ";
	if (($end_y!="") && ($end_m!="") && ($end_d!="")) {
		$i_max = $end_y;
		$M = $end_m;
		$D = $end_d;
	} else {
		$i_max = date("Y");
		$M = date("m");
		$D = date("d");
	}
	echo "	<SELECT class=\"year\" name='end_y'>\n";
	for ($i=2004 ; $i<=$i_max ; $i++) {
		echo "	<OPTION value='$i' SELECTED>$i</OPTION>\n";
	}
	echo "	</SELECT>\n";
	echo "	<SELECT name='end_m'>\n";
	for ($i=1 ; $i<=12 ; $i++) {
		if ($i<10) {
			if ($M!=$i) {
				echo "	<OPTION value='0$i'>0$i</OPTION>\n";
			} else {
				echo "	<OPTION value='0$i' SELECTED>0$i</OPTION>\n";
			}
		} else {
			if ($M!=$i) {
				echo "	<OPTION value='$i'>$i</OPTION>\n";
			} else {
				echo "	<OPTION value='$i' SELECTED>$i</OPTION>\n";
			}
		}
	}
	echo "	</SELECT>\n";
	echo "	<SELECT name='end_d'>\n";
	for ($i=1 ; $i<=31 ; $i++) {
		if ($i<10) {
			if ($D!=$i) {
				echo "	<OPTION value='0$i'>0$i</OPTION>\n";
			} else {
				echo "	<OPTION value='0$i' SELECTED>0$i</OPTION>\n";
			}
		} else {
			if ($D!=$i) {
				echo "	<OPTION value='$i'>$i</OPTION>\n";
			} else {
				echo "	<OPTION value='$i' SELECTED>$i</OPTION>\n";
			}
		}
	}
	echo "	</SELECT>\n";
	echo "	 <input type='submit' value='Find'>\n";
	echo "	</FORM>\n";
	echo "</div>\n";
}
?>
