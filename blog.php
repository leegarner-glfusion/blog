<?php
//  $Id: blog.php 13 2011-03-25 21:26:59Z root $
/**
*   Table names and other global configuraiton values.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** @global array $_TABLES */
global $_TABLES;
/** @global string $_DB_table_prefix */
global $_DB_table_prefix;

// Static configuration items
$_BLOG_CONF['pi_version']       = '0.0.1';
$_BLOG_CONF['pi_name']          = 'blog';
$_BLOG_CONF['gl_version']       = '1.3.0';
$_BLOG_CONF['pi_url']           = 'http://www.glfusion.org';
$_BLOG_CONF['pi_display_name']  = 'Blog';

$BLOG_prefix = $_DB_table_prefix . $_BLOG_CONF['pi_name'] . '_';

// Table definitions
$_TABLES['blogs']           = $BLOG_prefix . 'items';
$_TABLES['blog_submission'] = $BLOG_prefix . 'submission';

?>
