<?php
//  $Id: mysql_install.php 19 2011-04-04 17:15:51Z root $
/**
*   MySQL Table definitions for the Article plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

$_SQL['blogs'] = "CREATE TABLE {$_TABLES['blogs']} (
  `sid` varchar(40) NOT NULL default '',
  `author_id` mediumint(8) NOT NULL default '1',
  `draft_flag` tinyint(3) unsigned default '0',
  `tid` varchar(20) NOT NULL default 'General',
  `date` datetime default NULL,
  `title` varchar(128) default NULL,
  `introtext` text,
  `bodytext` text,
  `hits` mediumint(8) unsigned NOT NULL default '0',
  `rating` float NOT NULL default '0',
  `votes` int(11) NOT NULL default '0',
  `numemails` mediumint(8) unsigned NOT NULL default '0',
  `comments` mediumint(8) unsigned NOT NULL default '0',
  `comment_expire` datetime NOT NULL default '0000-00-00 00:00:00',
  `trackbacks` mediumint(8) unsigned NOT NULL default '0',
  `related` text,
  `featured` tinyint(3) unsigned NOT NULL default '0',
  `show_topic_icon` tinyint(1) unsigned NOT NULL default '1',
  `commentcode` tinyint(4) NOT NULL default '0',
  `trackbackcode` tinyint(4) NOT NULL default '0',
  `statuscode` tinyint(4) NOT NULL default '0',
  `expire` datetime NOT NULL default '0000-00-00 00:00:00',
  `postmode` varchar(10) NOT NULL default 'html',
  `advanced_editor_mode` tinyint(1) unsigned default '0',
  `frontpage` tinyint(3) unsigned default '0',
  `owner_id` mediumint(8) NOT NULL default '2',
  `group_id` mediumint(8) NOT NULL default '13',
  `perm_owner` tinyint(1) unsigned NOT NULL default '3',
  `perm_group` tinyint(1) unsigned NOT NULL default '2',
  `perm_members` tinyint(1) unsigned NOT NULL default '2',
  `perm_anon` tinyint(1) unsigned NOT NULL default '2',
  PRIMARY KEY  (`sid`),
  KEY `blog_tid` (`tid`),
  KEY `blog_author` (`author_id`),
  KEY `blog_featured` (`featured`),
  KEY `blog_hits` (`hits`),
  KEY `blog_statuscode` (`statuscode`),
  KEY `blog_expire` (`expire`),
  KEY `blog_date` (`date`),
  KEY `blog_frontpage` (`frontpage`)
)";

$_SQL['blog_submission'] = "CREATE TABLE `{$_TABLES['blog_submission']}` (
  `sid` varchar(20) NOT NULL default '',
  `author_id` mediumint(8) unsigned NOT NULL default '1',
  `tid` varchar(20) NOT NULL default 'General',
  `title` varchar(128) default NULL,
  `introtext` text,
  `bodytext` text,
  `date` datetime default NULL,
  `postmode` varchar(10) NOT NULL default 'html',
  PRIMARY KEY  (`sid`)
)";

?>
