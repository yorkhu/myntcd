<?php

/**
* @file
* Administration page callbacks for the myntcd module.
*/
/**
* Form builder. Configure myntcd.
*
* @ingroup forms
* @see system_settings_form().
*/

/*
 * Settings for the myntcd module
 * @return form contents for this module
 */
function myntcd_settings() {
	// Myntcd SQL szerver bealitasa
	$form['myntcd_sql_server'] = array(
		'#type' => 'fieldset',
		'#title' => t('Mysql Server Settings'),
		'#collapsible' => TRUE,
		'#collapsed' => FALSE,
	);
	$sql_server_type['mysql'] = 'MySQL';
  $sql_server_type['pgsql'] = 'PostgreSQL';
  // $sql_server_type['sqlite'] = 'SQLite';
  $form['myntcd_sql_server']['myntcd_server_type'] = array(
    '#type' => 'radios',
    '#title' => t('Type'),
    '#default_value' => variable_get('myntcd_server_type', 'mysql'),
    '#options' => $sql_server_type,
    '#description' => '',
  );

	$form['myntcd_sql_server']['myntcd_server'] = array(
		'#type' => 'textfield',
		'#title' => t('Host'),
		'#default_value' => variable_get('myntcd_server', 'localhost'),
		'#description' => '',
		'#maxlength' => '200',
		'#size' => '30',
	);

	$form['myntcd_sql_server']['myntcd_port'] = array(
		'#type' => 'textfield',
		'#title' => t('Port'),
		'#default_value' => variable_get('myntcd_port', '3306'),
		'#description' => '',
		'#maxlength' => '5',
		'#size' => '5',
	);

	$form['myntcd_sql_server']['myntcd_db'] = array(
		'#type' => 'textfield',
		'#title' => t('Database name'),
		'#default_value' => variable_get('myntcd_db', 'traffic'),
		'#description' => '',
		'#size' => '10',
	);

	$form['myntcd_sql_server']['myntcd_user'] = array(
		'#type' => 'textfield',
		'#title' => t('Username'),
		'#default_value' => variable_get('myntcd_user', 'user'),
		'#description' => '',
		'#size' => '10',
	);

	$form['myntcd_sql_server']['myntcd_pwd'] = array(
		'#type' => 'password',
		'#title' => t('Password'),
		'#description' => '',
		'#size' => '10',
	);

	// RRD adatbazis hasznalatahoz szukseges beallitasok
	$form['myntcd_rrd'] = array(
		'#type' => 'fieldset',
		'#title' => t('RRD Tool settings'),
		'#collapsible' => TRUE,
		'#collapsed' => FALSE,
	);

	$form['myntcd_rrd']['myntcd_rrd_cmd'] = array(
		'#type' => 'textfield',
		'#title' => t('RRD command'),
		'#default_value' => variable_get('myntcd_rrd_cmd', '/usr/bin/rrdtool'),
		'#description' => '',
		'#maxlength' => '200',
		'#size' => '30',
	);

	$form['myntcd_rrd']['myntcd_rrd_dir'] = array(
		'#type' => 'textfield',
		'#title' => t('RRD file dir'),
		'#default_value' => variable_get('myntcd_rrd_dir', '/usr/local/myntcd/rrd'),
		'#description' => '',
		'#maxlength' => '200',
		'#size' => '30',
	);

	return system_settings_form($form);
} // function myntcd_settins()
