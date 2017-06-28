<?php
//  $Id: ajax.php 2 2009-12-30 04:11:52Z root $
/**
*   Administrative AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only.  It's called by Javascript,
// so don't try to display a message
if (!SEC_hasRights('blog.admin')) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the blog admin ajax function.");
    exit;
}

switch ($_GET['action']) {
case 'toggle':
    USES_blog_class_blog();
    switch ($_GET['component']) {
    case 'featured':
    case 'draft_flag':
    case 'frontpage':
        $newval = Blog::toggle($_GET['component'], $_GET['oldval'], $_GET['sid']);
        break;

    default:
        exit;
    }

    header('Content-Type: text/xml');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

    echo '<?xml version="1.0" encoding="ISO-8859-1"?>'."\n";
    echo '<info>'. "\n";
    echo "<newval>$newval</newval>\n";
    echo "<sid>{$_GET['sid']}</sid>\n";
    echo "<component>{$_GET['component']}</component>\n";
    echo "<baseurl>" . BLOG_ADMIN_URL . "</baseurl>\n";
    echo "</info>\n";
    break;

}

?>
