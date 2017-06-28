<?php
//  $Id: index.php 20 2011-04-04 17:16:27Z root $
/**
 *  Main public entry point for the blog plugin
*
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 *  @package    blog
 *  @version    0.0.1
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 *  @filesource
 */

/**
* glFusion common function library
*/
require_once '../lib-common.php';
USES_blog_class_blogitem();

if ($_CONF['trackback_enabled']) {
    USES_lib_trackback();
}

// MAIN
$content = '';
$page_title = $LANG_BLOG['pi_name'];
$headercode = '';
$action = '';

// Retrieve and sanitize arguments and form vars
if (isset($_POST['sid'])) {
    $sid = COM_sanitizeId($_POST['sid']);
    $action = isset($_POST['what']) ? $_POST['what'] : 
            (isset($_POST['mode']) ? $_POST['mode'] : '');
    $page = 1;      // Just in case, we should have a default
} else {
    COM_setArgNames(array('sid', 'mode'));
    $sid = COM_applyFilter(COM_getArgument('sid'));
    $action = COM_applyFilter(COM_getArgument('mode'));
    $page = COM_applyFilter(COM_getArgument('page'));
}
if (empty($action)) {
    $expected = array(
        // Actions:
        'submit', 'savesubmission', 'sendblog', 
        // Views:
        'view', 'list', 'emailblog', 'print',
    );
    foreach ($expected as $provided) {
        if (isset($_POST[$provided])) {
            $action = $provided;
            break;
        } elseif (isset($_GET[$provided])) {
            $action = $provided;
            break;
        }
    }
}

// If a sid is given, then we want to do something with it...
// If no sid is given, all we can do is list them.
if (empty($sid)) {
    $view = 'list';
} elseif (empty($view)) {
    $view = $action;
}

$query = isset($_GET['query']) ? COM_applyFilter($_GET['query']) : '';
//$reply = isset($_GET['reply']) ? COM_applyFilter($_GET['reply']) : '';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
switch ($order) {
case 'DESC':
    $order = 'DESC';
    break;
case 'ASC':
default:
    $order = 'ASC';
    break;
}

$ratedIds = array();
if ($_CONF['rating_enabled'] != 0) {
    $ratedIds = RATING_getRatedIds($_BLOG_CONF['pi_name']);
}

switch ($action) {
case 'savesubmission':
    USES_blog_class_blogsubmission();
    $Blog = new BlogSubmission();
    if ($Blog->error) break;
    $status = $Blog->Save($_POST);
    $msg = $status ? 1 : 2;
    echo COM_refresh(BLOG_URL . '/index.php?msg='.$msg);
    exit;
    break;

case 'sendblog':
    // Send an article via email
    $title = $LANG_BLOG['mail_article'];
    if (empty($_POST['to']) || empty($_POST['toemail']) ||
        empty($_POST['from']) || empty($_POST['fromemail']) ||
        empty($_POST['shortmsg'])) {
        $content .= BLOG_mailform($sid);
    } else {
        $shortmsg = $_POST['shortmsg'];
        $to = $_POST['to'];
        $toemail = $_POST['toemail'];
        $from = $_POST['from'];
        $fromemail = $_POST['fromemail'];

        $Blog = new BlogItem($sid);
        if ($Blog->error) {
            echo COM_refresh($_CONF['site_url'] . '/index.php');
            exit;
        } else {
            $content .= $Blog->Send($to, $toemail, $from, $fromemail, $shortmsg);
        }
    }
    $view = 'view';
    break;

default:
    //$view = $action;
    break;
}

switch ($view) {
case 'print':
    $Blog = new BlogItem($sid);
    if ($Blog->error) {
        COM_404();
    }

    if ($_CONF['hideprintericon'] == 0) {
        $T = new Template(BLOG_PI_PATH . '/templates');
        $T->set_file('blog', 'printable.thtml');

        if ($_CONF['hidestorydate'] != 1) {
            $T->set_var('story_date', $Blog->displayElements('date'));
        }

        $blogUrl = COM_buildUrl(BLOG_URL . '/index.php?sid=' . $Blog->sid);

        if ($Blog->bodytext != '') {
            $T->set_var('story_bodytext', PLG_replaceTags($Blog->bodytext));
        }
        $T->set_var(array(
            'story_introtext'   => PLG_replaceTags($Blog->introtext),
            'site_slogan'       => $_CONF['site_slogan'],
            'story_id'          => $Blog->sid,
            'direction'         => $LANG_DIRECTION,
            'page_title'        => $_CONF['site_name'] . ': ' . 
                                    $Blog->displayElements('title'),
            'story_title'       => $Blog->DisplayElements('title'),
            'lang_full_blog'    => $LANG08[33],
            'blog_url'          => $blogUrl,
        ) );

        /*if ($Blog->commentcode >= 0) {
            $commentsUrl = $blogUrl . '#comments';
            $comments = $Blog->DisplayElements('comments');
            $numComments = COM_numberFormat ($comments);
            $T->set_var ('story_comments', $numComments);
            $T->set_var ('comments_url', $commentsUrl);
            $T->set_var ('comments_text', $numComments . ' ' . $LANG01[3]);
            $T->set_var ('comments_count', $numComments);
            $T->set_var ('lang_comments', $LANG01[3]);
            $comments_with_count = sprintf ($LANG01[121], $numComments);

            if ($comments > 0) {
                $comments_with_count = COM_createLink($comments_with_count, $commentsUrl);
            }
            $T->set_var ('comments_with_count', $comments_with_count);
        }*/
        COM_setLangIdAndAttribute($T);

        $T->parse('output', 'blog');
        header('Content-Type: text/html; charset=' . COM_getCharset ());
        $display = $T->finish($T->get_var('output'));
        echo $display;
        exit;
    }
    break;

case 'view':            // Displaying a single article
default:
    if (empty($sid)) {
        echo COM_refresh($_CONF['site_url'] . '/index.php');
        exit;
    }

    $today = date('Y-m-d H:i:s');
    $Blog = new BlogItem($sid);
    // Check if a Blog could be read, and if it's a draft.  Only the
    // item owner can view a draft (need to check if this is even needed
    // here instead of in the admin function).
    if ( $Blog->error || 
        ($Blog->draft_flag == 1 && $Blog->owner_id != $_USER['uid']) ||
        $Blog->date > $today ) {
        COM_404();
    }

    $permalink = COM_buildUrl(BLOG_URL . '/index.php?sid=' .
                $Blog->sid . '&amp;mode=view');

    $headercode .= '<link rel="canonical" href="'.$permalink.'"'.XHTML.'>';
    if ($Blog->trackbackcode == 0) {
        if ($_CONF['trackback_enabled']) {
            $tb_url = TRB_makeTrackbackUrl($Blog->sid, $_BLOG_CONF['pi_name']);
            $headercode .= LB . '<!--' . LB
                . TRB_trackbackRdf($permalink, $Blog->title, $tb_url)
                . LB . '-->' . LB;
        }
        if ($_CONF['pingback_enabled']) {
            header('X-Pingback: ' . $_CONF['site_url'] . '/pingback.php');
        }
    }

    $Blog->UpdateHits();

    $T = new Template(BLOG_PI_PATH . '/templates');
    $T->set_file('blog', 'displayblog.thtml');

    $T->set_var('story_id', $Blog->sid);
    $T->set_var('story_title', $Blog->title);

    // Create the options available for this article
    $Blog_options = array();

    // Create the "Email this article" link
    if (    $_CONF['hideemailicon'] == 0 && 
            (!empty($_USER['username']) || 
                ( $_CONF['loginrequired'] == 0 &&
                $_CONF['emailstoryloginrequired'] == 0 )
            )
    ) {
        $emailUrl = BLOG_URL . '/index.php?mode=emailblog&amp;sid=' .
                    $Blog->sid;
        $Blog_options[] = COM_createLink($LANG11[2], $emailUrl,
                    array('rel' => 'nofollow'));
        $T->set_var(array(
                'email_story_url'   => $emailUrl,
                'lang_email_story'  => $LANG11[2],
                'lang_email_story_alt' => $LANG01[64],
        ) );
    }

    // Create the "Printable View" link
    if ($_CONF['hideprintericon'] == 0) {
        $printUrl = COM_buildUrl(BLOG_URL . '/index.php?sid='.
                    $Blog->sid . '&amp;mode=print');
        $Blog_options[] = COM_createLink($LANG11[3], $printUrl, 
                    array('rel' => 'nofollow'));
        $T->set_var(array(
                'print_story_url'   => $printUrl,
                'lang_print_story'  => $LANG11[3],
                'lang_print_story_alt' => $LANG01[65],
        ) );
    }

    // Create the RSS subscription links
    if ($_CONF['backend'] == 1) {
        $tid = $Blog->tid;
        $result = DB_query("SELECT filename, title, format 
                    FROM {$_TABLES['syndication']} 
                    WHERE type = 'blog' 
                    AND topic = '".DB_escapeString($tid)."' 
                    AND is_enabled = 1");
        //$feeds = DB_numRows($result);
        while ($A = DB_fetchArray($result, false)) {
            //for ($i = 0; $i < $feeds; $i++) {
            //list($filename, $title, $format) = DB_fetchArray($result);
            $feedUrl = SYND_getFeedUrl($A['filename']);
            $feedTitle = sprintf($LANG11[6], $A['title']);
            $feedType = SYND_getMimeType($A['format']);
            $Blog_options[] = COM_createLink($feedTitle, $feedUrl,
                                        array('type'  => $feedType,
                                        'class' => ''));
        }
    }

    // All options created, now create the options blocks at the bottom
    // of the article, only if there are some options to show
    if (count($Blog_options) > 0) {
        $optionsblock = COM_startBlock($LANG11[4], '',
                COM_getBlockTemplate('story_options_block', 'header'), 'story-options')
                . COM_makeList($Blog_options, 'list-story-options')
                . COM_endBlock(COM_getBlockTemplate('story_options_block',
                    'footer'));
    } else {
        $optionsblock = '';
    }

    // Create the "What's Related" links
    $related = $Blog->WhatsRelated();
    if (!empty($related)) {
        $related = COM_startBlock($LANG11[1], '',
                    COM_getBlockTemplate('whats_related_block', 'header'), 
                        'whats-related') . 
                $related . 
                COM_endBlock(COM_getBlockTemplate('whats_related_block',
                    'footer'));
    }

    $T->set_var(array(
            'whats_related'     => $related,
            'story_options'     => $optionsblock,
            'whats_related_story_options' => $related . $optionsblock,
            'formatted_article' => $Blog->Render(BLOG_FULL, $query, $page),
    ) );

    // display comments or not?
    // TODO: "mode" contains the page number?
    if ($Blog->commentcode >= 0) {
        if ((is_numeric($mode)) and ($_CONF['allow_page_breaks'] == 1)) {
            $Blog_page = $mode;
            $mode = '';
            if ($Blog_page <= 0) {
                $Blog_page = 1;
            }
            $blog_arr = explode('[page_break]', $Blog->bodytext);
            $conf = $_CONF['page_break_comments'];
            if  (   $conf == 'all' ||
                    ( $conf == 'first' && $Blog_page == 1 ) ||
                    ( $conf == 'last' && count($blog_arr) == $Blog_page)
            ) {
                $show_comments = true;
            } else {
                $show_comments = false;
            }
        } else {
            // On the first or only page, show the comments
            $show_comments = true;
        }

        // Display the comments, if there are any ..
        if ($show_comments) {
            $delete_option = ($Blog->isAdmin || $Blog->checkAccess(3)) ? 
                    true : false;
            USES_lib_comments();
            if (isset($_GET['mode'])) {
                $mode = COM_applyFilter($_GET['mode']);
            } elseif (isset($_POST['mode'])) {
                $mode = COM_applyFilter($_POST['mode']);
            } else {
                $mode = '';
            }
            if (isset($_GET['page'])) {
                $page = (int)COM_applyFilter($_GET['page'], true);
            } else {
                $page = 1;
            }

            $T->set_var('commentbar',
                CMT_userComments($Blog->sid, $Blog->title, 
                    $_BLOG_CONF['pi_name'],
                    $order, $mode, 0, $page, false, $delete_option, 
                    $Blog->commentcode, $Blog->uid));
        }

    }   // if blog->commentcode >= 0

    if ($_CONF['trackback_enabled'] && ($Blog->trackbackcode >= 0)) {
        if (SEC_hasRights('story.ping')) {
            if (($Blog->draft_flag == 0) &&
                        ($Blog->date < time ())) {
                $url = $_CONF['site_admin_url'] . 
                            '/trackback.php?mode=sendall&amp;id=' . $Blog->sid;
                $T->set_var('send_trackback_link',
                            COM_createLink($LANG_TRB['send_trackback'], $url));
                $T->set_var(array(
                            'send_trackback_url'    => $url,
                            'lang_send_trackback_text' =>
                                        $LANG_TRB['send_trackback'],
                ) );
            }
        }

        $permalink = COM_buildUrl(BLOG_URL . '/index.php?sid=' .
                        $Blog->sid . '&mode=view');
        $T->set_var('trackback', TRB_renderTrackbackComments($Blog->sid, 
                            $_BLOG_CONF['pi_name'],
                            $Blog->title, $permalink));
    } else {
       $T->set_var('trackback', '');
    }

    $content .= $T->finish($T->parse('output', 'blog'));
    break;

case 'emailblog':
    // Show the email form to send an article
    $page_title = $LANG_BLOG['mail_article'];
    $content .= BLOG_mailform($sid);
    break;

case 'list':
    // List blogs, both featured & unfeatured
    $content .= BLOG_showIntros(BLOG_ALL);
    break;
}

$display = BLOG_siteHeader($page_title, $headercode);
if (isset($_GET['msg'])) {
    $msg = (int)$_GET['msg'];
    if ($msg > 0) {
        $plugin = isset($_GET['plugin']) ? 
                COM_applyFilter($_GET['plugin']) : $_BLOG_CONF['pi_name'];
    }
    $display .= COM_showMessage($msg, $plugin);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;

?>
