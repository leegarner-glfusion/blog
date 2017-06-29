<?php
/**
*   Administration entry point for the Blog plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';

USES_blog_class_blogitem();

$display = '';

if (!SEC_hasRights('blog.edit')) {
    $display = COM_siteHeader ('menu', $LANG_BLOG['access_denied']);
    $display .= COM_startBlock ($LANG_BLOG['access_denied'], '',
                        COM_getBlockTemplate ('_msg_block', 'header'));
    $display .= $LANG_BLOG['access_denied_msg'];
    $display .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
    $display .= COM_siteFooter ();
    COM_accessLog ("User {$_USER['username']} tried to illegally access the static pages administration screen.");
    echo $display;
    exit;
}

$action = '';
$expected = array(
    'mode', 'edit', 'moderate', 'approvesubmission', 'preview', 'clone', 
    'save', 'delete', 'cancel',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST['provided'];
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET['provided'];
    }
}

$sid = '';
if (isset($_POST['sid'])) {
    $sid = COM_sanitizeId($_POST['sid']);
} elseif (isset($_GET['sid'])) {
    $sid = COM_sanitizeId($_GET['sid']);
}

switch ($action) {
case 'clone':
    if (!empty($bid)) {
        SEC_setCookie($_CONF['cookie_name'].'fckeditor', 
                    SEC_createTokenGeneral('advancededitor'),
                        time() + 1200, $_CONF['cookie_path'],
                        $_CONF['cookiedomain'], $_CONF['cookiesecure'],false);
        $display .= COM_siteHeader('menu', $LANG_BLOG['staticpageeditor']);
        $display .= BLOG_Submit();
        $display .= COM_siteFooter();
    } else {
        $display = COM_refresh ($_CONF['site_admin_url'] . '/index.php');
    }
    break;

case 'save':
    $Blog = new BlogItem($sid, 'admin');
    $Blog->Save($_POST);
    $view = 'list';
    break;

case 'delete':
    $Blog = new BlogItem($sid);
    $Blog->Delete();
    $view = 'list';
    break;

case 'approvesubmission':
    $Blog = new BlogItem();
    $status = $Blog->Save($_POST);
    if ($status)
        DB_delete($_TABLES['blog_submission'], 'sid', $sid);
    echo COM_refresh($_CONF['site_admin_url'] . '/moderation.php');
    exit;
    break;

default:
    $view = $action;

}

switch ($view) {
case 'edit':
case 'preview':
    SEC_setCookie($_CONF['cookie_name'].'fckeditor', 
                SEC_createTokenGeneral('advancededitor'),
                        time() + 1200, $_CONF['cookie_path'],
                        $_CONF['cookiedomain'], $_CONF['cookiesecure'],false);
    $display .= COM_siteHeader('menu', $LANG_BLOG['editor']);
    $Blog = new BlogItem($sid);
    if (!$Blog->error) {
        if ($view == 'preview') {
            // For preview, the article hasn't been saved yet so populate 
            // all the fields from $_POST before trying to display it.
            $Blog->SetVars($_POST);
        }
        $display .= $Blog->Edit($view);
    }
    $display .= COM_siteFooter();
    break;

case 'moderate':
    USES_blog_class_blogsubmission();
    SEC_setCookie($_CONF['cookie_name'].'fckeditor', 
                SEC_createTokenGeneral('advancededitor'),
                        time() + 1200, $_CONF['cookie_path'],
                        $_CONF['cookiedomain'], $_CONF['cookiesecure'],false);
    $display .= COM_siteHeader('menu', $LANG_BLOG['editor']);
    $Sub = new BlogSubmission($sid);
    if (!$Sub->error) {
        $Blog = new BlogItem('', 'submission');
        if (!$Blog->error) {
            // there aren't too many fields in the submission, we'll just
            // set them manually.
            $Blog->author_id    = $Sub->author_id;
            $Blog->tid          = $Sub->tid;
            $Blog->title        = $Sub->title;
            $Blog->introtext    = $Sub->introtext;
            $Blog->bodytext     = $Sub->bodytext;
            $Blog->date         = $Sub->date;
            $Blog->postmode     = $Sub->postmode;
            $Blog->owner_id     = $Blog->author_id;
            $display .= $Blog->Edit($view);
        }
    }
    $display .= COM_siteFooter();
    break;

case 'list':
default:
    $display .= COM_siteHeader('menu', $LANG_BLOG['bloglist']);
    $display .= BLOG_adminList();
    $display .= COM_siteFooter ();
    break;
}

echo $display;
exit;

/**
*   List blogs
*/
function BLOG_adminList()
{
    global $_CONF, $_TABLES, $_IMAGE_TYPE, $LANG_ADMIN, $LANG_ACCESS, 
        $LANG_BLOG;

    USES_lib_admin();

    $outputHandle = outputHandler::getInstance();
    $outputHandle->addScriptFile($_CONF['path_html'].'blog/js/toggle.js');

    $retval = '';

    $menu_arr = array (
        array('url' => BLOG_ADMIN_URL . '/index.php?edit=x',
              'text' => $LANG_ADMIN['create_new']),
        array('url' => $_CONF['site_admin_url'],
              'text' => $LANG_ADMIN['admin_home'])
    );

    $retval .= COM_startBlock($LANG_BLOG['bloglist'], '',
                              COM_getBlockTemplate('_admin_block', 'header'));

    $retval .= ADMIN_createMenu($menu_arr, $LANG_BLOG['instructions'], 
                plugin_geticon_blog());

    $header_arr = array(
        array('text' => $LANG_ADMIN['edit'], 'field' => 'edit', 
                'sort' => false, 'align' => 'center'),
        array('text' => $LANG_ADMIN['copy'], 'field' => 'copy', 
                'sort' => false, 'align' => 'center'),
        array('text' => $LANG_STATIC['id'], 'field' => 'sid', 
                'sort' => true),
        array('text' => $LANG_ADMIN['title'], 'field' => 'title', 
                'sort' => true),
        array('text' => $LANG_BLOG['author'], 'field' => 'uid', 
                'sort' => true),
        array('text' => $LANG_BLOG['draft'], 'field' => 'draft_flag', 
                'sort' => true, 'align' => 'center'),
        array('text' => $LANG_BLOG['featured'], 'field' => 'featured', 
                'sort' => true, 'align' => 'center'),
        array('text' => $LANG_BLOG['frontpage'], 'field' => 'frontpage',
                'sort' => true, 'align' => 'center'),
        /*array('text' => $LANG_ACCESS['access'], 'field' => 'access', 
                'sort' => false, 'align' => 'center'),*/
        array('text' => $LANG_BLOG['date'], 'field' => 'unixdate', 
                'sort' => true, 'align' => 'center'),
        array('text' => $LANG_ADMIN['delete'], 'field' => 'delete', 
                'sort' => false, 'align' => 'center'),
    );

    $defsort_arr = array('field' => 'title', 'direction' => 'ASC');

    $text_arr = array(
        'has_extras' => true,
        'form_url' => BLOG_ADMIN_URL . '/index.php',
    );

    // sql query which drives the list
    $sql = "SELECT *, UNIX_TIMESTAMP(date) AS unixdate 
            FROM {$_TABLES['blogs']} 
            WHERE 1=1 ";

    $query_arr = array(
        'table' => 'blog',
        'sql' => $sql,
        'query_fields' => array('title', 'introtext', 'bodytext'),
        'default_filter' => COM_getPermSQL('AND')
    );

    // create the security token, and embed it in the list form
    // also set the hidden var which signifies that this list allows for pages
    // to be enabled/disabled via checkbox
    $token = SEC_createToken();
    $form_arr = array(
        'top'    => '<input type="hidden" name="'.CSRF_TOKEN.'" value="'.$token.'"/>',
        'bottom' => '<input type="hidden" name="staticpageenabler" value="true"' . XHTML . '>'
    );

    $retval .= ADMIN_list('blogs', 'BLOG_getListField',
                    $header_arr, $text_arr, $query_arr, $defsort_arr, 
                    '', $token, '', $form_arr);
    $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));

    return $retval;

}


function BLOG_getListField($fieldname, $fieldvalue, $A, $icon_arr, $token)
{
    global $_CONF, $LANG_ADMIN, $LANG_BLOG, $LANG_ACCESS;

    $retval = '';
    $access = SEC_hasAccess($A['owner_id'],$A['group_id'],$A['perm_owner'],
                    $A['perm_group'],$A['perm_members'],$A['perm_anon']);
    $retval = '';

    switch($fieldname) {
    case 'edit':
        if ($access == 3) {
            $attr['title'] = $LANG_ADMIN['edit'];
            $retval = COM_createLink(
                    $icon_arr['edit'],
                    BLOG_ADMIN_URL . '/index.php?edit=x&amp;sid=' . 
                    $A['sid'], $attr );
        }
        break;

    case 'delete':
        if ($access == 3) {
            $attr = array('title' => $LANG_ADMIN['delete'],
                'onclick' => "return confirm('" . $LANG_BLOG['delete_confirm'] . "');",
                'alt' => $LANG_ADMIN['delete'],
            );
            $retval = COM_createLink(
                $icon_arr['delete'],
                BLOG_ADMIN_URL . '/index.php?delete=x&amp;sid=' . 
                    $A['sid'] . '&amp;' . CSRF_TOKEN . '=' . $token, $attr);
        }
        break;

    case 'copy':
        if ($access >= 2) {
            $attr['title'] = $LANG_ADMIN['copy'];
            $retval = COM_createLink(
                    $icon_arr['copy'],
                    BLOG_ADMIN_URL . '/index.php?clone=x&amp;sid=' . 
                        $A['sid'], $attr);
        }
        break;

    case 'title':
        $url = COM_buildUrl(BLOG_URL . '/index.php?sid=' . 
                urlencode($A['sid']));
        $retval = COM_createLink($A['title'], $url,
                    array('title' => $A['title']));
        break;

    case 'uid':
        $retval = COM_getDisplayName($A['uid']);
        break;

    case 'unixdate':
        $d = new Date($A['unixdate']);
        $retval = $d->format($_CONF['daytime'], true);
        break;

    case 'featured':
    case 'draft_flag':
    case 'frontpage':
        if ($fieldvalue == 1) {
            $switch = BLOG_CHECKED;
            $enabled = 1;
        } else {
            $switch = '';
            $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" 
                name=\"{$fieldname}\"
                id=\"{$fieldname}_{$A['sid']}\"
                onclick='BLOG_toggle(this,\"{$A['sid']}\",\"$fieldname\");'/>".LB;
        /*if ($fieldvalue == 1) {
            $retval = $icon_arr['check'];
        } */
        break;

    default:
        $retval = $fieldvalue;
        break;
    }
    return $retval;
}


?>
