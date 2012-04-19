#! /usr/bin/php
<?php
$config['rrd_dir'] = '/usr/local/myntcd/myntcd/rrd/';
$config['rrd_dump_dir'] = '/usr/local/myntcd/myntcd/rrd-dump/';
$config['rrd_cmd'] = '/usr/bin/rrdtool';

/* Konyvtar megnyitasa */
if ($dir = @opendir($config['rrd_dir'])) {
        /* Beolvassa a konyvtarban levo fileokat.*/
        $files = array();
        $i = 0;
        while ( ($file = readdir($dir)) ) {
                if ($file != '.' && $file != '..' && !is_dir($config['rrd_dir'].$file)) {
                        if (!is_file($config['rrd_dump_dir'].$file.'.xml')) {
                                $i++;
                                echo $i.' - '.$config['rrd_cmd'].' dump '.$config['rrd_dir'].$file.' > '.$config['rrd_dump_dir'].$file.'.xml'."\n";
                                exec($config['rrd_cmd'].' dump '.$config['rrd_dir'].$file.' > '.$config['rrd_dump_dir'].$file.'.xml', $error );
                        }
                }
        }
}

?>
