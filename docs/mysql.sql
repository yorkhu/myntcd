CREATE DATABASE traffic;

use traffic;

DROP TABLE IF EXISTS `now`;
CREATE TABLE `now` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `ip` varchar(15) default NULL,
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `type` int(3) NOT NULL default '0',
  `in_packet` int(10) unsigned NOT NULL default '0',
  `out_packet` int(10) unsigned NOT NULL default '0',
  `in_byte` bigint(20) unsigned NOT NULL default '0',
  `out_byte` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=0 ;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `daily`;
CREATE TABLE `daily` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `ip` varchar(15) default NULL,
  `date` date NOT NULL default '0000-00-00',
  `type` int(3) NOT NULL default '0',
  `in_packet` int(10) unsigned NOT NULL default '0',
  `out_packet` int(10) unsigned NOT NULL default '0',
  `in_byte` bigint(20) unsigned NOT NULL default '0',
  `out_byte` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=0 ;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `filter`;
CREATE TABLE `filter` (
  `ip` varchar(15) NOT NULL default '',
  `blacklist` smallint(1) unsigned NOT NULL default '0',
  `date` date NOT NULL default '0000-00-00',
  `comment` varchar(255) NOT NULL,
  PRIMARY KEY  (`ip`)
) ENGINE=MyISAM;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `tmp`;
CREATE TABLE `tmp` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `ip` varchar(15) default NULL,
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `type` int(3) NOT NULL default '0',
  `in_packet` int(10) unsigned NOT NULL default '0',
  `out_packet` int(10) unsigned NOT NULL default '0',
  `in_byte` bigint(20) unsigned NOT NULL default '0',
  `out_byte` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=0;
