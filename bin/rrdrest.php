#! /usr/bin/php
<?php
$config['rrd_dir'] = '/usr/local/myntcd/rrd/';
$config['rrd_dump_dir'] = '/usr/local/myntcd/xml/';
$config['rrd_cmd'] = '/usr/bin/rrdtool';

function xml2rrd($file) {
    return str_replace('.xml', '', $file);
}

/* Konyvtar megnyitasa */
if ($dir = @opendir($config['rrd_dump_dir'])) {
	/* Beolvassa a konyvtarban levo fileokat.*/
        $files = array();
        $i = 0;
        while ( ($file = readdir($dir)) ) {
                if ($file != '.' && $file != '..' && !is_dir($config['rrd_dump_dir'].xml2rrd($file))) {
                        if (!is_file($config['rrd_dir'].xml2rrd($file))) {
                                $i++;
                                echo $i.' - '.$config['rrd_cmd'].' restore '.$config['rrd_dump_dir'].$file.' '.$config['rrd_dir'].xml2rrd($file)."\n";
                                exec($config['rrd_cmd'].' restore '.$config['rrd_dump_dir'].$file.' '.$config['rrd_dir'].xml2rrd($file), $error );
                        }
                }
        }
}

?>

