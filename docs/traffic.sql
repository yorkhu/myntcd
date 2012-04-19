--
-- Adatbázis: `traffic`
--
CREATE DATABASE `traffic`;
USE `traffic`;

-- --------------------------------------------------------

--
-- Tábla szerkezet: `daily`
--

CREATE TABLE IF NOT EXISTS `daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(39) DEFAULT NULL,
  `ipr_id` smallint(5) unsigned NOT NULL,
  `date` date NOT NULL DEFAULT '0000-00-00',
  `type` int(3) NOT NULL DEFAULT '0',
  `in_packet` int(10) unsigned NOT NULL DEFAULT '0',
  `out_packet` int(10) unsigned NOT NULL DEFAULT '0',
  `in_byte` bigint(20) unsigned NOT NULL DEFAULT '0',
  `out_byte` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  AUTO_INCREMENT=0 ;

-- --------------------------------------------------------

--
-- Tábla szerkezet: `filter`
--

CREATE TABLE IF NOT EXISTS `filter` (
  `ip` varchar(39) NOT NULL DEFAULT '',
  `blacklist` smallint(1) unsigned NOT NULL DEFAULT '0',
  `date` date NOT NULL DEFAULT '0000-00-00',
  `comment` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`ip`)
) ENGINE=MyISAM ;

-- --------------------------------------------------------

--
-- Tábla szerkezet: `ipranges`
--

CREATE TABLE IF NOT EXISTS `ipranges` (
  `ipr_id` smallint(6) NOT NULL AUTO_INCREMENT,
  `iprange` varchar(39) NOT NULL,
  `mask` varchar(39) NOT NULL,
  PRIMARY KEY (`ipr_id`)
) ENGINE=MyISAM  AUTO_INCREMENT=0 ;

-- --------------------------------------------------------

--
-- Tábla szerkezet: `now`
--

CREATE TABLE IF NOT EXISTS `now` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(39) DEFAULT NULL,
  `ipr_id` smallint(5) unsigned NOT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `type` int(3) NOT NULL DEFAULT '0',
  `in_packet` int(10) unsigned NOT NULL DEFAULT '0',
  `out_packet` int(10) unsigned NOT NULL DEFAULT '0',
  `in_byte` bigint(20) unsigned NOT NULL DEFAULT '0',
  `out_byte` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  AUTO_INCREMENT=0 ;

-- --------------------------------------------------------

--
-- Tábla szerkezet: `tmp`
--

CREATE TABLE IF NOT EXISTS `tmp` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(39) DEFAULT NULL,
  `ipr_id` smallint(5) unsigned NOT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `type` int(3) NOT NULL DEFAULT '0',
  `in_packet` int(10) unsigned NOT NULL DEFAULT '0',
  `out_packet` int(10) unsigned NOT NULL DEFAULT '0',
  `in_byte` bigint(20) unsigned NOT NULL DEFAULT '0',
  `out_byte` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  AUTO_INCREMENT=0 ;
