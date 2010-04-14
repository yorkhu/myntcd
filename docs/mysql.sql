CREATE DATABASE traffic;

use traffic;

CREATE TABLE rrd_now (
  id bigint(20) unsigned NOT NULL auto_increment,
  ip varchar(15) default NULL,
  date datetime NOT NULL default '0000-00-00 00:00:00',
  type int(3) NOT NULL default '0',
  in_count int(10) unsigned NOT NULL default '0',
  out_count int(10) unsigned NOT NULL default '0',
  in_byte bigint(20) unsigned NOT NULL default '0',
  out_byte bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) TYPE=MyISAM AUTO_INCREMENT=0 ;

CREATE TABLE rrd_hourly (
  id bigint(20) unsigned NOT NULL auto_increment,
  ip varchar(15) default NULL,
  date datetime NOT NULL default '0000-00-00 00:00:00',
  type int(3) NOT NULL default '0',
  in_count int(10) unsigned NOT NULL default '0',
  out_count int(10) unsigned NOT NULL default '0',
  in_byte bigint(20) unsigned NOT NULL default '0',
  out_byte bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) TYPE=MyISAM AUTO_INCREMENT=0 ;

CREATE TABLE rrd_daily (
  id bigint(20) unsigned NOT NULL auto_increment,
  ip varchar(15) default NULL,
  date datetime NOT NULL default '0000-00-00 00:00:00',
  type int(3) NOT NULL default '0',
  in_count int(10) unsigned NOT NULL default '0',
  out_count int(10) unsigned NOT NULL default '0',
  in_byte bigint(20) unsigned NOT NULL default '0',
  out_byte bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) TYPE=MyISAM AUTO_INCREMENT=0 ;

CREATE TABLE blacklist (
    ip varchar(15) NOT NULL default '',
    date date NOT NULL default '0000-00-00',
    PRIMARY KEY  (ip)
) TYPE=MyISAM;
      

CREATE TABLE whitelist (
  ip varchar(15) NOT NULL default '',
  date date NOT NULL default '0000-00-00',
  PRIMARY KEY  (ip)
) TYPE=MyISAM;

