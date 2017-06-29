<?php
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

switch ($_POST['action']) {
case 'toggle':
    USES_blog_class_blogitem();
    switch ($_POST['type']) {
    case 'featured':
    case 'draft_flag':
    case 'frontpage':
        $newval = BlogItem::toggle($_POST['type'], $_POST['oldval'], $_POST['id']);
        break;

    default:
        exit;
    }
    break;
}

$output = json_encode(array(
    'id' => $_POST['id'],
    'type' => $_POST['type'],
    'newval' => $newval,
    'statusMessage' => 'Updated',
) );
header('Content-Type: text/xml');
header("Cache-Control: no-cache, must-revalidate");
//A date in the past
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
echo $output;
exit;

?>
