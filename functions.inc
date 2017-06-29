<?php
/**
*   glFusion API functions for the Blog plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined('GVERSION')) {
    die('This file can not be used on its own.');
}

include_once $_CONF['path_system'] . 'classes/topic.class.php';
Topic::Current($topic);

// Check and see if we need to load the plugin configuration
if (!isset($_BLOG_CONF['loginrequired'])) {
    require_once $_CONF['path_system'] . 'classes/config.class.php';
    $bl_config = config::get_instance();
    $_BLOG_CONF = $bl_config->get_config('blog');
}

/** Additional config items and table definitions are added here */
require_once __DIR__ . '/blog.php';

// Define paths and urls
define('BLOG_PI_PATH', 
        $_CONF['path'].'plugins/'.$_BLOG_CONF['pi_name'].'/');
define('BLOG_URL', $_CONF['site_url'].'/'.$_BLOG_CONF['pi_name']);
define('BLOG_ADMIN_URL', 
        $_CONF['site_admin_url']. '/plugins/'.$_BLOG_CONF['pi_name']);

// Blog viewing modes
define('BLOG_COMPACT',   1);
define('BLOG_PREVIEW',   2);
define('BLOG_FULL',      4);
define('BLOG_PRINT',     8);
define('BLOG_EMAIL',    16);

// Just to make the code easier & more consistent
define('BLOG_CHECKED', 'checked="checked"');
define('BLOG_SELECTED', 'selected="selected"');

define('BLOG_FEATURED',     1);     // Indicates featured blogs only
define('BLOG_NONFEATURED',  2);     // Indicates non-featured blogs only
define('BLOG_ALL',          3);     // shorthand for (FEATURED | NONFEATURED)

// Expiration options.  Values of 10 & 11 are for compatibility with the
// legacy lib-story values
define('BLOG_EXPIRE_NONE',     0);
define('BLOG_EXPIRE_ARCHIVE',  10);
define('BLOG_EXPIRE_DELETE',   11);

if (file_exists(__DIR__ . '/config.php')) {
    /** Include local configuration overrides */
    include_once __DIR__ . 'config.php';
}

// Now we'll set some global variables that get used a lot, to save
// some DB lookups and function calls
//$_BLOG_CONF['archivetid'] =
//            DB_getItem($_TABLES['topics'], 'tid', "archive_flag=1");
$_BLOG_CONF['today'] = date('Y-m-d H:i:s');

// Global 'loginrequired' overrides the plugin's configuration
if ($_CONF['loginrequired']) $_BLOG_CONF['loginrequired'] = 1;

// Include the user's language file, if found.  Default to englsh.php.
$langpath = BLOG_PI_PATH . '/language';
$langfile = $_CONF['language'] . '.php';
if (!is_file("$langpath/$langfile")) {
    $langfile = 'english.php';
}
require_once "$langpath/$langfile";
if (is_file("$langpath/custom/$langfile")) {
    /** Include the custom language file, if any */
    include_once "$langpath/custom/$langfile";
}


/*function USES_blog_functions()
{   require_once BLOG_PI_PATH . 'blog_functions.inc.php';  }*/

function USES_blog_class_blog()
{   require_once BLOG_PI_PATH . 'classes/Blog.class.php';  }

function USES_blog_class_blogitem()
{   require_once BLOG_PI_PATH . 'classes/BlogItem.class.php';  }

function USES_blog_class_blogsubmission()
{   require_once BLOG_PI_PATH . 'classes/BlogSubmission.class.php';  }

function USES_blog_class_blogimage()
{   require_once BLOG_PI_PATH . 'classes/BlogImage.class.php';  }


/**
*   Returns the items for this plugin that should appear on the main menu.
*
*   @return   mixed   menu entry, or boolean false if disabled / hidden
*/
function plugin_getmenuitems_blog()
{
    global $_BLOG_CONF, $LANG_BLOG;

    if (($_BLOG_CONF['hideblogmenu'] == 1) || 
            (COM_isAnonUser() && $_BLOG_CONF['loginrequired'])) {
        return false;
    }

    return array($LANG_BLOG['pi_title'] => BLOG_URL . '/index.php');
}


/**
*   Return headlines for New Blog section in the What's New block, if enabled.
*
*   @return mixed   array(headline, byline), or boolean false is disabled
*/
function plugin_whatsnewsupported_blog()
{
    global $_BLOG_CONF, $LANG_BLOG, $LANG_WHATSNEW;

    $rows = plugin_getwhatsnew_blog(false);
    if ( $_BLOG_CONF['hidenewblog'] == 0 ||
        ($_BLOG_CONF['hidenewblog'] == 2 && $rows > 0) ) {
        $retval = array($LANG_BLOG['pi_title'], '<small>'
                        . COM_formatTimeString($LANG_WHATSNEW['new_last'],
                                $_BLOG_CONF['newbloginterval'] * 86400)
                        . '</small>'
                       );
    } else {
        $retval = false;
    }

    return $retval;
}


/**
*   Return new Blog items for the What's New block.
*
*   @param  boolean $getlist    True to get the items, False for just a count
*   @return string      HTML list of new items
*/
function plugin_getwhatsnew_blog($getlist=true)
{
    global $_CONF, $_TABLES, $_BLOG_CONF, $LANG_BLOG;

    $retval = '';

    // Get newest items
                //(date >= (DATE_SUB(NOW(), INTERVAL {$_BLOG_CONF['newbloginterval']} DAY))) " . 
    $sql = "SELECT sid,title 
            FROM {$_TABLES['blogs']} 
            WHERE 
                (date >= (DATE_SUB(NOW(), INTERVAL 14 DAY))) " . 
                COM_getPermSQL( 'AND' ) . 
            ' ORDER BY date DESC LIMIT 15';
    //echo $sql;die;
    $result = DB_query($sql);
    $nrows = DB_numRows($result);

    // Just return the count, for plugin_whatsnewsupported_blog()
    if (!$getlist) {
        return $nrows;
    }

    if ($nrows > 0) {
        $newblog = array();
        while ($A = DB_fetchArray($result, false)) {
            $A['title'] = htmlspecialchars($A['title']);
            $title = COM_truncate($A['title'], $_CONF['title_trim_length'],'...');
            $newblog[] = COM_createLink($title, BLOG_URL.'/index.php?sid='.
                $A['sid']);
        }

        $retval .= COM_makeList($newblog, 'list-new-plugins');
    } else {
        $retval .= $LANG_BLOG[88] . '<br' . XHTML . '>' . LB;
    }

    return $retval;
}


/**
*   Show the frontpage articles using the "centerblock" calls.
*   To simulate prior behaviour, the Featured article is displayed when the
*   position is "top", and non-featured articles are displayed when the 
*   position is "after featured".  There's no display for the "bottom".
*   TODO: Figure out plugin ordering.  Another plugin's "after featured" block
*       might get pushed below our non-featured stories depending on the order
*       in which plugins are loaded.
*
*   @param  integer $where  1 = top, 2 = after feat. story, 3 = bottom of page
*   @param  integer $page
*   @param  string  $topic  Topic ID of the current page
*   @return string  HTML for the page
*/
function plugin_centerblock_blog($where, $page, $topic)
{
    global $_BLOG_CONF;

    // This only applies to the frontpage
    if (($where != 0 && $page != 1) || $_BLOG_CONF['onfrontpage'] == 0) {
        return '';
    }

    switch ($where) {
    case 0:     // Replacing home page
        $retval = BLOG_siteHeader();
        $retval .= BLOG_showIntros(BLOG_ALL);
        $retval .= BLOG_siteFooter();
        break;
    case 1:     // Top
        $retval = BLOG_showIntros(BLOG_FEATURED);
        break;
    case 2:     // After featured story
        $retval = BLOG_showIntros(BLOG_NONFEATURED);
        break;
    case 3:     // Bottom of page, we do nothing
        break;
    }

    return $retval;
}


/**
* Implements the [blog:] autotag.
*
* @param    string  $op         operation to perform
* @param    string  $content    item (e.g. story text), including the autotag
* @param    array   $autotag    parameters used in the autotag
* @param    mixed               tag names (for $op='tagname') or formatted content
*
*/
function plugin_autotags_blog($op, $content = '', $autotag = '')
{
    global $_CONF, $_TABLES, $LANG_DIRECTION;

    $repl_text = '';
    $sid = '';

    switch ($op) {
    case 'tagname':
        return array('blog');
        break;
    case 'parse':
        $action = $autotag['parm1'];
        $px = explode(' ', trim($autotag['parm2']));
        $skip = 0;

        // Some defaults
        $list_type = BLOG_ALL;
        $count = 1;

        if (is_array($px)) {
            foreach ($px as $part) {
                if (strstr($part, ':') !== false) {
                    $a = explode(':', $part);
                    switch ($a[0]) {
                    case 'id':          // specify the item to show
                        $sid = COM_applyFilter($a[1]);
                        $skip++;
                        break;
                    case 'count':       // the number of items to list
                        $count = (int)$a[1];
                        $skip++;
                        break;
                    }
                } else {
                    // handle parameters with no value (directives)
                    switch ($part) {
                    case 'featured':
                        $list_type = BLOG_FEATURED;
                        $skip++;
                        break;
                    case 'nonfeatured':
                        $list_type = BLOG_NONFEATURED;
                        $skip++;
                        break;
                    }
                }
            }
        }

        // Any leftover parts become the caption.  Borrowed from
        // Mediagallery's caption handling.
        if ($skip != 0) {
            if (count($px) > $skip) {
                for ($i = 0; $i < $skip; $i++) {
                    array_shift($px);
                }
                $caption = trim(implode(' ', $px));
            } else {
                $caption = '';
            }
        } else {
            // There weren't any control parameters, so all of parm2 is 
            // the caption.
            $caption = trim($autotag['parm2']);
        }

        USES_blog_class_blogitem();

        switch ($action) {
        case 'link':
            if (empty($sid)) break;

            $url = COM_buildUrl(BLOG_URL . '/index.php?sid=' . $sid);
            if ($caption == '') {   // no caption, get the title value
                $caption = DB_getItem($_TABLES['blogs'], 'title',
                            "sid='$sid'");
            }
            /*$class = 'ext-link';
            if ((!empty($LANG_DIRECTION)) && ($LANG_DIRECTION == 'rtl')) {
                $class .= '-rtl';
            }*/
            $attr = array(
                    'title' => $url,
                    'class' => $class,
                     );
            $repl_text = COM_createLink($caption, $url, $attr);
            break;

        case 'compact':
            // Insert the Compact view for one or more item.  Includes
            // title, byline, links to read-more & comments, etc.
            if (empty($sid)) {
                // No ID given, get the ones that match other options
                $sids = BlogItem::getSids($list_type, $count);
                foreach ($sids as $sid) { 
                    $Blog = new BlogItem($sid);
                    if ($Blog->error) continue;
                    $repl_text .= $Blog->Render(BLOG_COMPACT);
                }
            } else {
                $Blog = new BlogItem($sid);
                if ($Blog->error) break;
                $repl_text = $Blog->Render(BLOG_COMPACT);
            }
            break;

        case 'intro':
            // Insert the intro text only
            $Blog = new BlogItem($sid);
            if ($Blog->error) break;
            $repl_text = $Blog->DisplayElements('introtext');
            break;

        case 'full':
            // Insert the complete blog text, no titles or other links.
            $Blog = new BlogItem($sid);
            if ($Blog->error) break;
            $repl_text = $Blog->DisplayElements('introtext') . 
                $Blog->DisplayElements('bodytext');
            break;

        case 'list':
            // Show a list of blog items, like the site's homepage.
            // This can be dangerous, since it can be used in a blog's
            // intro section, causing recursion.
            $repl_text = BLOG_showIntros($list_type, $count);
            break;

        }
        break;
    }

    //if (!empty($repl_text)) {
        $content = str_replace($autotag['tagstr'], $repl_text, $content);
    //}

    return $content;

}


/**
*   Get the currently installed version.
*
*   @return string  version number
*/
function plugin_chkVersion_blog()
{
    global $_BLOG_CONF;

    return $_BLOG_CONF['pi_version'];
}


/**
*   Helper function: count number of blog items
*
*   @return array   array(number of blog, number of clicks)
*/
function BLOG_countLinksAndClicks ()
{
    global $_TABLES;

    $result = DB_query("SELECT 
            COUNT(*) AS count,
            SUM(hits) AS clicks 
        FROM {$_TABLES['blogs']}" . COM_getPermSQL ());
    $A = DB_fetchArray ($result, false);
    $total_links = $A['count'];
    $total_clicks = $A['clicks'];
    if (empty ($total_clicks)) {
        $total_clicks = 0;
    }

    return array ($total_links, $total_clicks);
}


/**
*   Shows the statistics for the Blog plugin on stats.php.
*   If $showsitestats is 1 then we are to only print the overall stats in the
*   'site statistics box' otherwise we show the detailed stats
*
*   @param  integer $showsitestate  Flag to let us know which stats to get
*   @param  string                  HTML for the stats section
*/
function plugin_showstats_blog($showsitestats)
{
    global $_CONF, $_TABLES, $LANG_BLOG_STATS;

    USES_lib_admin();

    $retval = '';

    $result = DB_query ("SELECT sid, title, date 
                FROM {$_TABLES['blogs']}
                " . COM_getPermSQL() . "
                ORDER BY date DESC 
                LIMIT 10");
    $nrows  = DB_numRows ($result);
    if ($nrows > 0) {
        $header_arr = array(
            array('text'         => $LANG_BLOG_STATS['stats_page_title'],
                  'field'        => 'title',
                  'header_class' => 'stats-header-title'
            ),
            array('text'         => $LANG_BLOG_STATS['stats_date'],
                  'field'        => 'date',
                  'header_class' => 'stats-header-count',
                  'field_class'  => 'stats-list-count'
            )
        );
        $data_arr = array();
        $text_arr = array('has_menu' => false,
                          'title'    => $LANG_BLOG_STATS['stats_headline'],
        );
        while ($A = DB_fetchArray($result, false)) {
            $title = stripslashes(str_replace ('$', '&#36;', $A['title']));
            $date = substr($A['date'], 0, 10);
            $data_arr[] = array('title' => $title,
                                'date' => $date
                          );
        }
        $retval .= ADMIN_simpleList('', $header_arr, $text_arr, $data_arr);
    } else {
        $retval .= COM_startBlock($LANG_BLOG_STATS['stats_headline']);
        $retval .= $LANG_BLOG_STATS['stats_no_hits'];
        $retval .= COM_endBlock();
    }

    return $retval;
}


/**
*   New stats plugin API function for proper integration with the site stats
*
*   @return array   array(item text, item count)
*/
function plugin_statssummary_blog()
{
    global $LANG_BLOG_STATS;

    list($total_links, $total_clicks) = BLOG_countLinksAndClicks();

    $item_count = COM_NumberFormat ($total_links)
                . ' (' . COM_NumberFormat ($total_clicks) . ')';


    return array ($LANG_BLOG_STATS['blog'], $item_count);
}


/**
*   Get the name to show in the "type" dropdown in the advanced search.
*
*   @return array   array(plugin name=>entry title) pair for the dropdown
*
*/
function plugin_searchtypes_blog()
{
    global $LANG_BLOG, $_BLOG_CONF;

    return array($_BLOG_CONF['pi_name'] => $LANG_BLOG['pi_title']);
}


/**
* This searches for Blog items matching the user query and returns an array for the
* header and table rows back to search.php where it will be formated and printed
*
* @param    string  $query      Keywords user is looking for
* @param    date    $datestart  Start date to get results for
* @param    date    $dateend    End date to get results for
* @param    string  $topic      The topic they were searching in
* @param    string  $type       Type of items they are searching, or 'all'
* @param    int     $author     Get all results by this author
* @param    string  $keyType    search key type: 'all', 'phrase', 'any'
* @param    int     $page       page number of current search
* @param    int     $perpage    number of results per page
* @return   object              search result object
*
*/
function plugin_dopluginsearch_blog($query, $datestart, $dateend, 
    $topic, $type, $author, $keyType, $page, $perpage
) {
    global $_CONF, $_TABLES, $_BLOG_CONF, $LANG_BLOG;

    if ($type != $_BLOG_CONF['pi_name'] && $type != 'all') {
        return NULL;
    }

    if (SEC_hasRights('blog.admin')) {
        $permSQL = '';
    } else {
        $permSQL = COM_getPermSQL('AND');
    }

    $url = BLOG_URL . '/index.php?sid=';
    $q = '&query=' . urlencode($query);
    // blog search
    $sql = "SELECT  sid, title, introtext as description, hits, 
                    UNIX_TIMESTAMP(date) AS date,
                    CONCAT('$url', sid, '$q') AS url,
                    author_id as uid
            FROM {$_TABLES['blogs']} 
            WHERE date <> 1 "
            . COM_getPermSQL('AND', 0, 2);
    if (!empty($author))
        $sql .= " AND (owner_id = '" . (int)$author . "') ";

    $query = DB_escapeString(htmlspecialchars(trim($query)));

    $search = new SearchCriteria($_BLOG_CONF['pi_name'], 
                    $LANG_BLOG['pi_title']);
    $columns = array('title', 'introtext', 'bodytext',);
    $sql .= $search->getDateRangeSQL('AND', 'UNIX_TIMESTAMP(date)',
                    $datestart, $dateend);
    list($sql,$ftsql) = $search->buildSearchSQL($keyType, $query,
                    $columns, $sql);
    $search->setSQL($sql);
    $search->setFTSQL($ftsql);
    $search->setRank(5);

    return $search;

}


/**
*   Put an option in Command and Control for this plugin.
*
*   @return mixed   array(title, url, icon), or boolean false when not allowed
*/
function plugin_cclabel_blog()
{
    global $LANG_BLOG;

    if (plugin_ismoderator_blog()) {
        return array($LANG_BLOG['pi_title'],
                      BLOG_ADMIN_URL . '/index.php',
                      plugin_geticon_blog());
    } else {
        return false;
    }
}


/**
*   Get the administrator's menu option for this plugin
*
*   @return mixed   array(title, url, num. links), or void when not allowed
*/
function plugin_getadminoption_blog()
{
    global $LANG_BLOG, $_TABLES;
 
    if (plugin_ismoderator_blog()) {
        return array($LANG_BLOG['pi_title'],
                    BLOG_ADMIN_URL . '/index.php',
                    DB_count($_TABLES['blogs']),
                );
    } else {
        return false;
    }
}


/**
*   Actions to take if a user is deleted.
*
*   @param  integer $uid    User id of deleted user
*/
function plugin_user_delete_blog($uid)
{
    global $_TABLES, $_BLOG_CONF;

    if (DB_count ($_TABLES['blogs'], 'owner_id', $uid) == 0) {
        return;
    }

    if ($_BLOG_CONF['delete_blog'] == 1) {
        // delete the items
        DB_delete($_TABLES['blogs'], 'owner_id', $uid);
    } else {
        // assign ownership to a user from the Root group
        $rootgroup = DB_getItem($_TABLES['groups'], 'grp_id',
                                 "grp_name = 'Root'");
        $result = DB_query("SELECT DISTINCT ug_uid 
                    FROM {$_TABLES['group_assignments']} 
                    WHERE ug_main_grp_id = $rootgroup 
                    ORDER BY ug_uid 
                    LIMIT 1");
        $A = DB_fetchArray($result, false);
        $rootuser = $A['ug_uid'];
        DB_query("UPDATE {$_TABLES['blogs']} 
                SET owner_id = $rootuser 
                WHERE owner_id = $uid");
    }
}


/**
*   Do we support feeds?
*
*   @return array   id/name pairs of all supported feeds
*/
function plugin_getfeednames_blog()
{
    global $_TABLES;

    $feeds = array();
    $feeds[] = array ('id' => 'all', 'name' => 'all blog items');

    return $feeds;
}


/**
* Provide feed data
*
* @param    int     $feed       feed ID
* @param    ref     $link
* @param    ref     $update
* @return   array               feed entries
*
*/
function plugin_getfeedcontent_blog($feed, &$link, &$update)
{
    global $_CONF, $_TABLES;

    $result = DB_query("SELECT 
                    topic, limits, content_length 
                FROM {$_TABLES['syndication']} 
                WHERE fid = '$feed'");
    $S = DB_fetchArray($result, false);

    $result = DB_query("SELECT 
                    sid, owner_id, title, content,
                    UNIX_TIMESTAMP(modified) AS modified 
                FROM {$_TABLES['blogs']} 
                " . blog_buildSql($S['limits']));

    $content = array();
    $sids = array();

    while ($row = DB_fetchArray($result, false)) {
        $sids[] = $row['sid'];

        $blogtitle = $row['title'];
        $blogcontent = strip_tags($row['content']); // No HTML

        $content[] = array( 'title'   => $blogtitle,
                            'summary' => $blogcontent,
                            'uid'     => $row['owner_id'],
                            'author'  => COM_getDisplayName($row['owner_id']),
                            'date'    => $row['modified'],
                            'format'  => 'plaintext',
                          );
    }

    $link = BLOG_URL . '/index.php';
    $update = implode( ',', $sids );

    return $content;
}


/**
* Helper function: Build part of an SQL request
*
* @param    string  $limits     limit (number of entries or number of hours)
* @return   string              part of an SQL request
*
*/
function blog_buildSql($limits)
{
    $where = '';

    $limitsql = '';
    if (!empty ($limits)) {
        if (substr ($limits, -1) == 'h') { // last xx hours
            $limitsql = '';
            $hours = substr ($limits, 0, -1);
            if (!empty ($where)) {
                $where .= ' AND ';
            }
            $where .= "modified >= DATE_SUB(NOW(),INTERVAL $hours HOUR)";
        } else {
            $limitsql = ' LIMIT ' . $limits;
        }
    } else {
        $limitsql = ' LIMIT 10';
    }

    if (!empty ($where)) {
        $where = ' WHERE ' . $where;
    }

    $sql = $where . ' ORDER BY date DESC' . $limitsql;

    return $sql;
}


/**
*   Checking if Blog feeds are up to date
*
* @param    int     $feed           id of feed to be checked
* @param    string  $topic          topic (actually: cid)
* @param    string  $update_data    data describing current feed contents
* @param    string  $limit          number of entries or number of hours
* @param    string  $updated_type   (optional) type of feed to be updated
* @param    string  $updated_topic  (optional) feed's "topic" to be updated
* @param    string  $updated_id     (optional) id of entry that has changed
* @return   boolean                 true: feed data is up to date; false: isn't
*
*/
function plugin_feedupdatecheck_blog($feed, $topic, $update_data, $limit, $updated_type = '', $updated_topic = '', $updated_id = '')
{
    global $_TABLES, $_BLOG_CONF;

    $is_current = true;

    if ($updated_type != $_BLOG_CONF['pi_name']) {
        // we're not interested
        $updated_type = '';
        $updated_topic = '';
        $updated_id = '';
    }

    $sql = "SELECT sid FROM {$_TABLES['blogs']}" . blog_buildSql ($limit);
    $result = DB_query ($sql);
    $num = DB_numRows ($result);

    $sids = array ();
    for ($i = 0; $i < $num; $i++) {
        $A = DB_fetchArray($result, false);

        if ($A['sid'] == $updated_id) {
            // this feed has to be updated - no further checks needed
            return false;
        }

        $sids[] = $A['sid'];
    }
    $current = implode (',', $sids);

    return ($current != $update_data) ? false : true;
}


/**
*   Update the Blog plugin
*
*   @return integer Number of message to display (true = generic success msg)
*/
function plugin_upgrade_blog()
{
    // the plugin needs these function so complain when they don't exist
    if (!function_exists('PLG_uninstall') ||
            !function_exists('COM_createLink')) {
        return 3002;
    }

    // upgrades are done by the install script - return a generic error
    COM_errorLog("Plugin upgrade function not implemented");
    return 3001;
}


/**
*   Actions to take if this plugin is enabled or disabled.
*
*   @param  boolean $enable     true = enabled, false = disabled
*/
function plugin_enablestatechange_blog($enable)
{
    global $_TABLES, $_BLOG_CONF;

    $is_enabled = $enable ? 1 : 0;

    // toggle Blog feeds
    DB_query ("UPDATE {$_TABLES['syndication']} 
            SET is_enabled = $is_enabled 
            WHERE type = '{$_BLOG_CONF['pi_name']}'");

    // If disabling, disable the blocks.  If enabling, don't touch them,
    // they'll have to be re-enabled manually since we don't know which ones
    // were enabled before.
    if ($is_enabled == 0) {
        $blockfns = array('phpblock_blog', 'phpblock_latestblog');
        foreach ($blockfns as $blockfn) {
            DB_query("UPDATE {$_TABLES['blocks']}
                    SET is_enabled=$is_enabled
                    WHERE phpblockfn='$blockfn'");
        }
    }
}


/**
* Counts the items that are submitted
*
* @return   int     number of items in submission queue
*
*/
function plugin_submissioncount_blog()
{
    global $_TABLES;

    $retval = 0;

    if (plugin_ismoderator_blog()) {
        $retval = DB_count($_TABLES['blog_submission']);
    }

    return $retval;
}


/**
*   Checks that the current user has the rights to moderate blogs
*
*   @return boolean     Returns true if moderator
*/
function plugin_ismoderator_blog()
{
//TODO
return true;
    return SEC_hasRights('story.admin');
    return SEC_hasRights('blog.moderate,blog.admin', 'OR');
}


/**
*   Returns SQL & Language texts to moderation.php.
*
*   @return mixed   plugin object or void if not allowed
*/
function plugin_itemlist_blog()
{
    global $_TABLES, $LANG_BLOG;

    if (plugin_ismoderator_blog()) {
        $plugin = new Plugin();
        $plugin->submissionlabel = $LANG_BLOG['moderation_title'];
        $plugin->submissionhelpfile = 'cclinksubmission.html';
        $plugin->getsubmissionssql = "SELECT 
                    sid AS id, title, date, tid, uid
                    FROM {$_TABLES['blog_submission']} 
                    ORDER BY date ASC";
        $plugin->addSubmissionHeading($LANG_BLOG['title']);
        $plugin->addSubmissionHeading($LANG_BLOG['date']);
        $plugin->addSubmissionHeading($LANG_BLOG['topic']);

        return $plugin;
    }
}


/**
*   Returns list of moderation values.
*
*   The array returned contains (in order): the row 'id' label, main plugin
*   table, moderation fields (comma seperated), and plugin submission table
*
*   @return array   Returns array of useful moderation values
*/
function plugin_moderationvalues_blog()
{
    global $_TABLES;

    return array (
        'sid',
        $_TABLES['blogs'],
        'sid, uid, tid, title, introtext, bodytext, date, postmode',
        $_TABLES['blog_submission']
    );
}


/**
*   Performs plugin exclusive work for items approved by moderation.
*   This sets the permissions for the new blog entry to default values.
*
*   @param  string  $id     Identifying string
*   @return string          Any wanted HTML output
*/
function plugin_moderationapprove_blog($id)
{
    global $_TABLES, $_GROUPS, $_BLOG_CONF;

    if (isset($_GROUPS['Blog Admin'])) {
        $group_id = $_GROUPS['Blog Admin'];
    } else {
        $group_id = SEC_getFeatureGroup('blog.moderate');
    }

    $A = array();
    SEC_setDefaultPermissions($A, $_BLOG_CONF['default_permissions']);

    $owner_id = DB_getItem($_TABLES['blogs'], 'uid', "sid='$id'");
    DB_query("UPDATE {$_TABLES['blogs']} SET 
            group_id = '$group_id', 
            perm_owner = '{$A['perm_owner']}', 
            perm_group = '{$A['perm_group']}', 
            perm_members = '{$A['perm_members']}', 
            perm_anon = '{$A['perm_anon']}',
            owner_id = '$owner_id'
        WHERE sid = '$id'");

    return '';
}


/**
*   Performs plugin exclusive work for items deleted by moderation.
*   This deletes any entries from the submission table that have the
*   delete item's ID.  Shouldn't happen, but just in case...
*
*   @param  string  $id     Identifying string
*   @return string          Any wanted HTML output
*/
function plugin_moderationdelete_blog($id)
{
    global $_TABLES;

    DB_delete($_TABLES['blog_submission'], 'sid', $id);
    DB_delete($_TABLES['article_images'], 'ai_sid', $id);

    return '';
}


/**
*   Check submission form values and save if OK. Else show form again
*
*   @param  array   $A  The record
*   @return string      Any wanted HTML output
*/
function plugin_savesubmission_blog($A)
{
    global $LANG12, $_BLOG_CONF;

    $retval = '';

    if (!empty ($A['title']) && !empty ($A['content']) && !empty($A['date'])) {
        // OK, save it
        $retval = plugin_save_submit_blog($A);
    } else {
        // Problem, return an error message
        $retval .= COM_siteHeader()
            . COM_startBlock($LANG12[22], '', 
                        COM_getBlockTemplate('_msg_block', 'header'))
            . $LANG12[23]
            . COM_endBlock(COM_getBlockTemplate('_msg_block', 'footer'))
            . submissionform($_BLOG_CONF['pi_name'])
            . COM_siteFooter();
    }

    return $retval;
}


/**
*   Shows blog submission form.
*
*   @return   string  HTML for the blog submission form
*/
function plugin_submit_blog()
{
    USES_blog_class_blogsubmission();
    $Blog = new BlogSubmission();
    return $Blog->Edit();
}


/**
*   Saves a blog submission
*
*   @param    array   $A  Data for that submission
*   @return   string      HTML redirect
*/
function plugin_save_submit_blog($A)
{
    global $_CONF, $_TABLES, $_USER, $_BLOG_CONF, $LANG12, $_SERVER;

    $retval = '';

    $A['date'] = COM_applyFilter($A['date']);
    $A['title'] = glfPrepareForDB(COM_checkWords($A['title']));
    $A['content'] = glfPrepareForDB(COM_checkWords($A['content']));
    $A['sid'] = COM_makeSid();

    COM_updateSpeedlimit('submit', $_SERVER['REMOTE_ADDR']);
    if (COM_isAnonUser()) {
        $owner_id = 1; // anonymous user
    } else {
        $owner_id = $_USER['uid'];
    }

    if (($_BLOG_CONF['submission'] == 1) && !SEC_hasRights('blog.submit')) {
        $result = DB_save($_TABLES['blog_submission'],
                    'sid,title,content,date,modified,owner_id',
                    "{$A['sid']},'{$A['title']}','{$A['content']}','{$A['date']}',NOW(),$owner_id");

        if ($_BLOG_CONF['notification'] == 1) {
            blog_sendNotification($_TABLES['blog_submission'], $A);
        }

        $retval = COM_refresh($_CONF['site_url'] . '/index.php?msg=1&amp;plugin=blog');

    } else { // add Blog item directly

        if (SEC_hasRights('blog.submit')) {
            $A['group_id'] = SEC_getFeatureGroup('blog.submit');
        } else {
            $A['group_id'] = DB_getItem($_TABLES['groups'], 'grp_id', "grp_name = 'All Users'");
        }
        SEC_setDefaultPermissions($A, $_BLOG_CONF['default_permissions']);

        $result = DB_save($_TABLES['blogs'],
                    'sid,title,content,date,modified,owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon',
                    "{$A['sid']},'{$A['title']}','{$A['content']}','{$A['date']}',NOW(),$owner_id,{$A['group_id']},{$A['perm_owner']},{$A['perm_group']},{$A['perm_members']},{$A['perm_anon']}");

        if ($_BLOG_CONF['notification'] == 1) {
            blog_sendNotification($_TABLES['blogs'], $A);
        }
        COM_rdfUpToDateCheck($_BLOG_CONF['pi_name'], $A['sid']);

        $retval = COM_refresh(BLOG_URL . '/index.php?msg=4&amp;plugin=blogs');
    }

    return $retval;
}


/**
*   Send an email notification for a new submission.
*
*   @param  string  $table  Table where the new submission can be found
*   @param  array   $A      submission data
*/
function blog_sendNotification ($table, $A)
{
    global $_CONF, $_TABLES, $LANG_BLOG, $LANG_BLOG_SUBMIT, $LANG08;

    $title = stripslashes ($A['title']);
    $description = stripslashes ($A['description']);

    $mailbody = "$LANG_BLOG_SUBMIT[8]: $title\n"
              . "$LANG_BLOG_SUBMIT[9]: <{$A['url']}>\n"
              . "$LANG_BLOG_SUBMIT[3]: {$A['category']}\n\n"
              . $description . "\n\n";
    if ($table == $_TABLES['blog_submission']) {
        $mailbody .= "$LANG_BLOG[10] <{$_CONF['site_admin_url']}/moderation.php>\n\n";
    } else {
        $mailbody .= "$LANG_BLOG[114] <{$_CONF['site_url']}/blogs/index.php?category=" . urlencode ($A['category']) . ">\n\n";
    }
    $mailsubject = $_CONF['site_name'] . ' ' . $LANG_BLOG_SUBMIT[11];

    $mailbody .= "\n------------------------------\n";
    $mailbody .= "\n$LANG08[34]\n";
    $mailbody .= "\n------------------------------\n";

    COM_mail ($_CONF['site_mail'], $mailsubject, $mailbody);
}


/**
*   Returns the URL of the plugin's icon
*
*   @return string      URL of the icon
*/
function plugin_geticon_blog()
{
    return BLOG_URL . '/images/blog.png';
}


/**
*   Automatic removal function.
*
*   @return array       Array of items to be removed.
*/
function plugin_autouninstall_blog()
{
    $out = array (
        // give the name of the tables, without $_TABLES[]
        'tables' => array('blogs', 'blog_submission'),
        // give the full name of the group, as in the db
        'groups' => array('Blog Admin'),
        // give the full name of the feature, as in the db
        'features' => array('blog.edit', 'blog.moderate', 
                'blog.submit', 'blog.admin'),
        // give the full name of the block, including 'phpblock_', etc
        'php_blocks' => array('phpblock_blog', 'phpblock_latestblog'),
        // give all vars with their name
        'vars'=> array()
    );
    return $out;
}


/**
*   Indicate that the plugin does support comments.
*
*   @return boolean     True if comments supported, False if not.
*/
function plugin_commentsupport_blog()
{
    return true;
}


/**
*   Save a comment.
*
*   @param  string  $type       Plugin to save comment
*   @param  string  $title      comment title
*   @param  string  $comment    comment text
*   @param  string  $id         Item id to which $cid belongs
*   @param  int     $pid        comment parent
*   @param  string  $postmode   'html' or 'text'
*   @return mixed   false for failure, HTML string (redirect?) for success
*/
function plugin_savecomment_blog($title, $comment, $id, $pid, $postmode)
{
    global $_CONF, $_TABLES, $LANG03, $_USER, $_BLOG_CONF;

    $retval = '';

    $commentcode = DB_getItem($_TABLES['blogs'], 'commentcode',
                               "sid = '$id'");
    if ($commentcode != 0) {
        return COM_refresh($_CONF['site_url'] . '/index.php');
    }

    $ret = CMT_saveComment($title, $comment, $id, $pid, 
                $_BLOG_CONF['pi_name'], $postmode);
    if ($ret > 0) { // failure //FIXME: some failures should not return 
                    // to comment form
        $retval .= COM_siteHeader('menu', $LANG03[1])
                . CMT_commentForm($title, $comment, $id, $pid,
                    $_BLOG_CONF['pi_name'],$LANG03[14], $postmode)
                . COM_siteFooter();
    } else { // success
        $retval = COM_refresh(BLOG_URL . "/index.php?sid=$id&amp;aid=-1");
    }

    return $retval;
}


/**
*   Delete a comment.
*
*   @param  int     $cid    Comment to be deleted
*   @param  string  $id     Item id to which $cid belongs
*   @return mixed   false for failure, HTML string (redirect?) for success
*/
function plugin_deletecomment_blog($cid, $id)
{
    global $_CONF, $_TABLES, $_USER, $_BLOG_CONF;

    $retval = '';

    $has_editPermissions = SEC_hasRights('blog.edit');
    $result = DB_query("SELECT 
            owner_id, group_id, 
            perm_owner, perm_group, perm_members, perm_anon 
        FROM {$_TABLES['blogs']} 
        WHERE sid = '{$id}'");
    $A = DB_fetchArray($result, false);

    if ($has_editPermissions || SEC_hasAccess($A['owner_id'], $A['group_id'], 
            $A['perm_owner'], $A['perm_group'],
            $A['perm_members'], $A['perm_anon']) == 3) {
        CMT_deleteComment($cid, $id, $_BLOG_CONF['pi_name']);
        $retval .= COM_refresh(BLOG_URL . "/index.php?sid=$id&amp;aid=-1");
    } else {
        COM_errorLog("User {$_USER['username']} (IP: {$_SERVER['REMOTE_ADDR']}) "
                    . "tried to illegally delete comment $cid from blog $id");
        $retval .= COM_refresh($_CONF['site_url'] . '/index.php');
    }

    return $retval;
}


/**
*   Plugin should display [a] comment[s]
*
*   @param  string  $id     Unique idenifier for item comment belongs to
*   @param  int     $cid    Comment id to display (including sub-comments)
*   @param  string  $title  Page/comment title
*   @param  string  $order  'ASC' or 'DSC' or blank
*   @param  string  $format 'threaded', 'nested', or 'flat'
*   @param  int     $page   Page number of comments to display
*   @param  boolean $view   True to view comment (by cid), false (by $pid)
*   @return string          HTML to display comment
*/
function plugin_displaycomment_blog($id, $cid, $title, $order, $format, 
            $page, $view)
{
    global $_TABLES, $LANG_ACCESS, $_BLOG_CONF;

    $retval = '';

    $sql = "SELECT 
            COUNT(*) AS count, owner_id, group_id, 
            perm_owner, perm_group, perm_members, perm_anon
        FROM {$_TABLES['blogs']}
        WHERE 
            sid = '$id' "
            . COM_getPermSQL('AND') . 
        ' GROUP BY sid';
    $result = DB_query($sql);
    $A = DB_fetchArray($result, false);
    $allowed = $A['count'];

    if ($allowed == 1) {
        $delete_option = (SEC_hasRights('blog.edit') ||
                (SEC_hasAccess($A['owner_id'], $A['group_id'],
                    $A['perm_owner'], $A['perm_group'], 
                    $A['perm_members'], $A['perm_anon']) == 3));
        $retval .= CMT_userComments($id, $title, $_BLOG_CONF['pi_name'],
                        $order, $format, 
                        $cid, $page, $view, $delete_option);
    } else {
        $retval .= COM_startBlock($LANG_ACCESS['accessdenied'], '', 
                            COM_getBlockTemplate('_msg_block', 'header'))
                . $LANG_ACCESS['storydenialmsg']
                . COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
    }

    return $retval;
}

/**
*   Get comment url and unique id fieldname.
*
*   @return array   array containing main plugin URL and the unqiue id name
*/
function plugin_getcommenturlid_blog()
{
    return array(BLOG_URL . '/index.php', 'sid');
}


/**
*   Get additional CSS into the site header.
*   Allows for custom CSS to be placed by the site admin.
*   1 - If present, use the layout style *only*.
*   2 - Otherwise load the plugin style, and
*   3 - If present, load custom plugin styles
*
*   @return array   List of paths to CSS files
*/
function plugin_getheadercss_blog()
{
    global $_CONF, $_BLOG_CONF;

    $styles = array();
    $files = array('style.css');
    $layout_path = "{$_CONF['path_layout']}plugins/{$_BLOG_CONF['pi_name']}/";
    $pi_path = BLOG_PI_PATH . '/css/';

    foreach ($files as $file) {
        if (@file_exists($layout_path . $file)) {
            $styles[] = $layout_path . $file;
        } elseif (@file_exists($pi_path . $file)) {
            $styles[] = $pi_path . $file;
            // Add custom style override, if any
            if (@file_exists($pi_path . 'custom/' . $file)) {
                $styles[] = $pi_path . 'custom/' . $file;
            }
        }

    }

    return $styles;
}


/**
*   Get plugin-specific Javascript to go into every header
*
*   @return array       Array of file paths
*/
function plugin_getheaderjs_blog()
{
/*    global $_CONF;

    $files = array(
        $_CONF['path_html'] . '/blog/js/toggle.js',
    );
    return $files;*/
}


/**
*   Return true since this plugin supports webservices
*
*   @return boolean     True, if webservices are supported
*/
function plugin_wsEnabled_blog()
{
    return true;
}


/**
*   Scan for any stories that have expired and should be archived or deleted.
*   Articles to be archived are only retrieved if there is an archive TID,
*   otherwise they're left alone.
*/
function plugin_runScheduledTask_blog()
{
    global $_TABLES, $_BLOG_CONF;

    $asql = "SELECT sid,tid,title,expire,statuscode 
            FROM {$_TABLES['blogs']} 
            WHERE (expire <= NOW()) 
            AND (statuscode = '" . BLOG_EXPIRE_DELETE . "'";
    $archive_tid = Topic::archiveID();
    //if (empty($_BLOG_CONF['archivetid'])) {
    if (empty($archive_tid)) {
        $asql .= ')';
    } else {
        $asql .= ' OR statuscode = ' . BLOG_EXPIRE_ARCHIVE . ") 
            AND tid != '{$archivetid}'";
            //AND tid != '{$_BLOG_CONF['archivetid']}'";
    }
    $expiresql = DB_query($asql);

    USES_blog_class_blog();

    while (list ($sid, $expiretopic, $title, $expire, $statuscode) =
            DB_fetchArray($expiresql, false)) {
        if ($statuscode == BLOG_EXPIRE_ARCHIVE) {
                    //"Topic: {$_BLOG_CONF['archivetid']}, Title: $title, " .
            COM_errorLog("Archive Story: $sid, " . 
                    "Topic: {$archivetid}, Title: $title, " .
                    "Expired: $expire");
            DB_query("UPDATE {$_TABLES['blogs']} SET 
                            tid = '{$_BLOG_CONF['archivetid']}', 
                            frontpage = '0', 
                            featured = '0' 
                        WHERE sid='{$sid}'");
        } else if ($statuscode == BLOG_EXPIRE_DELETE) {
            COM_errorLog("Delete Story and comments: $sid, " .
                    "Topic: $expiretopic, Title: $title, Expired: $expire");
            BlogItem::Delete($sid);
        }

        CACHE_remove_instance($_BLOG_CONF['pi_name'] . '_' . $sid);
        CACHE_remove_instance('whatsnew');
    }

}


/**
*   Email new blog items to interested site members
*   Intented to be called from an external script.
*/
function plugin_emailNewItems_blog()
{
    global $_CONF, $_BLOG_CONF, $_TABLES, $LANG04, $LANG08, $LANG24;

    if ($_BLOG_CONF['emailnewitems'] == 0) {
        return;
    }

    $blogtext = '';
    $blogtext_text = '';

    $lastrun = DB_getItem($_TABLES['vars'], 'value', 
            "name = 'blog_lastemail'" );

    $subject = $_CONF['site_name'] . $LANG08[30] . strftime('%Y-%m-%d', time());
    // Holder for author names, to minimize calls to COM_getDisplayName()
    $authors = array();

    // We should be able to set these variables only once, as long as we
    // reset the user- and blog-specific variables in the loops that follow
    $T = new Template($_CONF['path_layout']);
    $T->set_file(array('message'    => 'digest.thtml',
                        'story'     => 'digest_story.thtml',
     ));

    $TT = new Template($_CONF['path_layout']);
    $TT->set_file(array('message'     => 'digest_text.thtml',
                        'story'       => 'digest_story_text.thtml',
    ) );

    $time = strftime($_CONF['shortdate'], time());
    $T->set_var(array(
        'week_date'     => $time,
        'site_name'     => $_CONF['site_name'],
        'remove_msg'    => sprintf($LANG08[36],
                        $_CONF['site_name'], $_CONF['site_url']),
    ) );

    $TT->set_var(array(
        'week_date'     => $time,
        'site_name'     => $_CONF['site_name'],
        'remove_msg'    => sprintf($LANG08[36],
                        $_CONF['site_name'], $_CONF['site_url']),
    ) );

    // Get users who want stories emailed to them
    $usersql = "SELECT 
            u.username, u.email, ui.etids, u.uid AS uuid, u.status
        FROM
            {$_TABLES['users']} u
        LEFT JOIN
            {$_TABLES['userindex']} ui
            ON ui.uid = u.uid
        WHERE u.uid > 1 
        AND u.status=" . USER_ACCOUNT_ACTIVE .
        " AND (ui.etids <> '-' OR ui.etids IS NULL) 
        ORDER BY u.uid";
    //echo $usersql;die;
    $users = DB_query($usersql);

    // For each user, pull the stories they want and email it to them
    while ($U = DB_fetchArray($users, false)) {

        $storysql  = 
            "SELECT sid, author_id, date AS day, title, introtext, bodytext";
        $commonsql = 
            " FROM {$_TABLES['blogs']} 
                WHERE draft_flag = 0 
                AND date <= NOW() 
                AND date >= '{$lastrun}'";

        // Get all the topics that this user has access to
        $topicsql  = "SELECT tid FROM {$_TABLES['topics']}"
                        . COM_getPermSQL('WHERE', $U['uuid']);
        $tresult   = DB_query($topicsql);
        $trows     = DB_numRows($tresult);

        if ($trows == 0) {
            // this user doesn't seem to have access to any topics ...
            continue;
        }

        $TIDS = array();
        while ($T = DB_fetchArray($tresult, false)) {
            $TIDS[] = $T['tid'];
        }

        // Get the tids that the user wants to see, and intersect them with
        // the ones that they're allowed to see
        if (!empty( $U['etids'])) {
            $ETIDS = explode(' ', $U['etids']);
            $TIDS = array_intersect($TIDS, $ETIDS);
        }

        if (sizeof($TIDS) > 0) {
            // restrict the item search to only interesting topics
            $commonsql .= " AND (tid IN ('" . implode("','", $TIDS) . "'))";
        } else {
            // No topics available.  Could happen in the user selected only
            // topic IDs that they no longer have access to.
            continue;
        }

        $commonsql .= COM_getPermSQL('AND', $U['uuid']) .
                ' ORDER BY featured DESC, date DESC';
        $storysql .= $commonsql;

        $stories = DB_query( $storysql );
        $nsrows  = DB_numRows( $stories );

        if( $nsrows == 0 ) {
            // If no new stories where pulled for this user, continue with next
            continue;
        }

        // Loop through stories building the requested email message
        while ($S = DB_fetchArray($stories, false)) {

            $url = COM_buildUrl(BLOG_URL . '/index.php?sid=' . $S['sid'] .
                    '&mode=view');
            $title     = COM_undoSpecialChars($S['title']);
            if ($_CONF['contributedbyline'] == 1) {
                if (empty($authors[$S['author_id']])) {
                    $storyauthor = COM_getDisplayName($S['author_id']);
                    $authors[$S['author_id']] = $storyauthor;
                } else {
                    $storyauthor = $authors[$S['author_id']];
                }
            }

            $story_date = strftime($_CONF['date'], strtotime($S['day']));

            if ($_CONF['emailstorieslength'] > 0) {
                $blogtext = COM_undoSpecialChars(strip_tags(
                        PLG_replaceTags($S['introtext'])));

                if ($_CONF['emailstorieslength'] > 1) {
                    $blogtext = COM_truncate($blogtext,
                                $_CONF['emailstorieslength'], '...' );
                }
            } else {
                $blogtext = '';
            }

            $T->set_var(array(
                'story_introtext' => $blogtext,
                'story_url'     => $url,
                'story_title'   => $title,
                'story_author'  => $storyauthor,
                'story_date'    => $story_date,
                'story_text'    => $blogtext,
            ) );
            $T->parse('digest_stories', 'story', true);

            $TT->set_var(array(
                'story_introtext' => $blogtext,
                'story_url'     => $url,
                'story_title'   => $title,
                'story_author'  => $storyauthor,
                'story_date'    => $story_date,
                'story_text'    => $blogtext_text,
            ) );
            $TT->parse('digest_stories', 'story', true);
        }

        $T->parse('digest', 'message', true);
        $TT->parse('digest', 'message', true);

        $mailtext = $T->finish($T->get_var('digest'));
        $mailtext_text = $TT->finish($TT->get_var('digest'));

        $mailfrom = $_CONF['noreply_mail'];
        $mailtext .= LB . LB . $LANG04[159];
        $mailtext_text .= LB . LB . $LANG04[159];

        $from = COM_formatEmailAddress('',$mailfrom);
        $to   = COM_formatEmailAddress($U['username'], $U['email']);
        COM_mail ($to, $subject, $mailtext, $from, 1, 0, '', $mailtext_text);

    }
    DB_query("UPDATE {$_TABLES['vars']}
            SET value =  NOW()
            WHERE name = 'blog_lastemail'");

}


/**
*   Check whether the current user is able to rate an item.
*
*   @param  string  $item_id    ID of item to be rated
*   @param  integer $uid        Current user ID
*   @return boolean             True if allowed, False if not
*/
function plugin_canuserrate_blog($item_id, $uid)
{
    global $_TABLES;

    $author = DB_getItem($_TABLES['blogs'], 'author_id', 
            "sid='" . DB_escapeString($item_id) . "'");
    if (empty($author) || $author == $uid) return false;
    else return true;
}


/**
*   Update the item's rating values
*
*   @param  string  $item_id    ID of item being rated
*   @param  float   $rating     New rating value
*   @param  integer $votes      New vote count
*/
function plugin_itemrated_blog($item_id, $rating, $votes)
{
    global $_TABLES;

    $item_id = DB_escapeString($item_id);
    $rating = (float)$rating;
    $votes = (int)$votes;
    DB_query("UPDATE {$_TABLES['blogs']} SET
            votes = $votes,
            rating = $rating
        WHERE sid = '$item_id'", 1);
}


/**
*   Get the blog intros, ready to display
*
*   @param  boolean $showfeatured   True to get only the featured article
*   @return string                  HTML for the blog into list
*/
function BLOG_showIntros($featured = BLOG_NONFEATURED, $limit = 0)
{
    global $_CONF, $_TABLES, $_BLOG_CONF, $_USER, $topic;

    USES_blog_class_blogitem();

    $retval = '';

    // Get the user's preferences, if not anonymous
    if (!COM_isAnonUser()) {
        $result = DB_query("SELECT maxstories, tids, aids 
                FROM {$_TABLES['userindex']} 
                WHERE uid = {$_USER['uid']}");
        $U = DB_fetchArray($result, false);
    } else {
        $U = array(
            'maxstories' => 0,
            'aids'      => '',
            'tids'      => '',
        );
    }

    $topiclimit = 0;
    $story_sort = $_BLOG_CONF['fpsort'];
    $story_sort_dir = $_BLOG_CONF['fpsortdir'];

    if (!empty($topic)) {
        $result = DB_query("SELECT limitnews,sort_by,sort_dir, topic
            FROM {$_TABLES['topics']} 
            WHERE tid='" . DB_escapeString($topic) . "'");
        if ($result) {
            list($topiclimit, $story_sort, $story_sort_dir, $topicname) = 
                DB_fetchArray($result);
        }
    }

    // Figure out the maximum number of stories to show, if a specific
    // limit wasn't provided as a parameter.
    if ($limit == 0) {
        if ($U['maxstories'] >= $_CONF['minnews']) { // 1. Get from user prefs
            $limit = (int)$U['maxstories'];
        }
        if ($limit == 0 && !empty($topic) &&
                $topiclimit >= $_CONF['minnews']) { // 2. Get the topic config
            $limit = (int)$topiclimit;
        }
        if ($limt == 0) {                           // 3. Use global config
            $limit = $_BLOG_CONF['perpage'];
        }
        if ($limit < 1) {       // Only happens if the config is invalid
            $limit = 10;
        }
    }

    // Retrieve the archive topic - currently only one supported
    //$archivetid = DB_getItem($_TABLES['topics'], 'tid', "archive_flag=1");
    $archivetid = Topic::archiveID();
    $where = ' (date <= NOW()) AND (draft_flag = 0) ';

    if (empty($topic)) {
        $where .= COM_getLangSQL('tid', 'AND', 'a');
    }

    // if a topic was provided only select those stories.
    if (!empty($topic)) {
        $where .= " AND a.tid = '" . DB_escapeString($topic) . "'";
    //} elseif (!$newstories) {
    //    $where .= ' AND a.frontpage = 1';
    } else {
        // Keep the archive topic off the frontpage unless specifically
        // requested.
        $where .= " AND a.tid != '{$archivetid}'";
    }   

    $where .= COM_getPermSQL('AND', 0, 2, 'a');

    if (!empty($U['aids'])) {       // Filter out authors
        $sql .= ' AND a.author_id NOT IN (' . 
                str_replace(' ', ',', $U['aids']) . ')';
    }

    if (!empty($U['tids'])) {       // Filter out topics
        $wherel .= " AND a.tid NOT IN ('" . 
                str_replace(' ', "','", $U['tids']) . "')";
    }

    $where .= COM_getTopicSQL('AND', 0, 'a');   // Verify permission

    /*if ($newstories) {
        $where .= " AND (date >= (date_sub(NOW(), INTERVAL {$_CONF['newstoriesinterval']} SECOND)))";
    }*/

    // Set the page
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    $offset = (int)(($page - 1) * $limit);

    // Set up the user field that we want in the SELECT statement
    $userfields = 'u.uid, u.username, u.fullname';
     if ($_CONF['allow_user_photo'] == 1) {
        $userfields .= ', u.photo';
        if ($_CONF['use_gravatar']) {
            $userfields .= ', u.email';
        }
    }

    // TODO: Configuration sort options on all pages
    switch ($story_sort) {
    case 1 :    // title
        $orderBy = ' title ';
        break;
    case 2 :    // ID
        $orderBy = ' sid ';
        break;
    case 0 :    // date
    default :
        $orderBy = ' date ';
        break;
    }
    switch ($story_sort_dir) {
    case 'ASC' :
        $orderBy .= ' ASC ';
        break;
    case 'DESC' :
    default :
        $orderBy .= ' DESC ';
        break;
    }

    $sql = "SELECT STRAIGHT_JOIN a.*, UNIX_TIMESTAMP(a.date) AS unixdate, 
                UNIX_TIMESTAMP(a.expire) as expireunix,
                $userfields, t.topic, t.imageurl 
        FROM {$_TABLES['blogs']} AS a, 
            {$_TABLES['users']} AS u,
            {$_TABLES['topics']} AS t 
        WHERE (a.author_id = u.uid)
            AND (a.tid = t.tid) 
            AND (a.frontpage = 1 || a.featured = 1)
            AND $where
        ORDER BY featured DESC, $orderBy 
        LIMIT $offset, $limit";

    //echo $sql;die;
    $result = DB_query($sql);
    $nrows = DB_numRows($result);

    $totalResult= DB_query("SELECT COUNT(*) AS count 
            FROM {$_TABLES['blogs']} AS a 
            WHERE" . $where);
    $D = DB_fetchArray($totalResult);
    $num_pages = ceil($D['count'] / $limit);

    // Get the featured blog first
    if ($A = DB_fetchArray($result, false)) {

        $Blog = new BlogItem($A['sid']);
        if ($_BLOG_CONF['showfirstasfeatured'] == 1) {
            $Blog->featured = 1;
        }

        // Display first blog, if it's featured & we're showing featured.
        // Also, since we've already retrieved the blog from the DB, we
        // might need to render it if it's not featured & we're showing the
        // non-featured blogs; otherwise it won't get shown when we loop
        // through the remaining records in the next step.
        if ( ($Blog->featured == 1 && ($featured & BLOG_FEATURED)) ||
                ( $Blog->featured == 0 && ($featured & BLOG_NONFEATURED)) ) {
            $retval .= $Blog->Render(BLOG_COMPACT);
        }

        // Get remaining articles
        if ($featured & BLOG_NONFEATURED) {
            while ($A = DB_fetchArray($result, false)) {
                $Blog = new BlogItem($A['sid']);
                if ($Blog->error) continue;
                $retval .= $Blog->Render(BLOG_COMPACT);
            }

            // Print Google-like paging navigation
            if (!isset($_CONF['hide_main_page_navigation']) ||
                    ($_CONF['hide_main_page_navigation'] == 0)) {
                if (empty($topic)) {
                    $base_url = BLOG_URL . '/index.php';
                    if ($newstories) {
                        $base_url .= '?display=new';
                    }
                } else {
                    $base_url = BLOG_URL . '/index.php?topic=' . $topic;
                }
                $retval .= COM_printPageNavigation($base_url, $page, $num_pages);
            }
        }
    }

    return $retval;

}


/**
*   Display a form to collect info for mailing an article to someone.
*
*   @param  string  $sid    Article ID
*   @return string          HTML for form
*/
function BLOG_mailform($sid)
{
    global $_CONF, $_TABLES, $_USER, $LANG03, $LANG_BLOG, $LANG_LOGIN;

    USES_blog_class_blogitem();

    $retval = '';

    // Check that the user has access to this area
    if (COM_isAnonUser() && $_BLOG_CONF['emailstoryloginrequired'] == 1) {
        $display  = COM_siteHeader('menu', $LANG_LOGIN[1]);
        $display .= SEC_loginRequiredForm();
        $display .= COM_siteFooter();
        echo $display;
        exit;
    }

    // Get the article.  Mainly to check the validity of $sid
    $Blog = new BlogItem($sid);
    if ($Blog->error) {
        return COM_refresh($_CONF['site_url'] . '/index.php');
    }

    // Get the field data from the previous submission, if any
    $to = isset($_POST['to']) ? COM_applyFilter($_POST['to']) : '';
    $toemail = isset($_POST['toemail']) ? COM_applyFilter($_POST['toemail']) : '';
    $from = isset($_POST['from']) ? COM_applyFilter($_POST['from']) : '';
    $fromemail = isset($_POST['fromemail']) ? COM_applyFilter($_POST['fromemail']) : '';
    $shortmsg = isset($_POST['shortmsg']) ? COM_applyFilter($_POST['shormsg']) : '';
    // Get the logged-in user's name & email, if any
    if (empty($from) && empty($fromemail) && !COM_isAnonUser()) {
        $from = COM_getDisplayName($_USER['uid'], $_USER['username'],
                                        $_USER['fullname']);
        $fromemail = DB_getItem($_TABLES['users'], 'email',
                                     "uid = {$_USER['uid']}");
    }

    $T = new Template(BLOG_PI_PATH . '/templates');
    $T->set_file('form', 'sendblog_form.thtml');

    $T->set_var(array(
        'start_block'       => COM_startBlock($LANG_BLOG['mail_article']),
        'name'              => $from,
        'email'             => $fromemail,
        'toname'            => $to,
        'toemail'           => $toemail,
        'shortmsg'          => htmlspecialchars($shortmsg),
        'blog_id'           => $sid,
        'action_url'        => BLOG_URL . '/index.php',
        'end_block'         => COM_endBlock(),
    ) );
    $T->parse('output', 'form');
    $retval .= $T->finish($T->get_var('output'));
    return $retval;
}


/**
*   Show the site header, with or without left blocks according to config.
*
*   @see    COM_siteHeader()
*   @param  string  $subject    Text for page title (article title, etc)
*   @param  string  $meta       Other meta info
*   @return string              HTML for site header
*/
function BLOG_siteHeader($subject='', $meta='')
{
    global $_BLOG_CONF, $LANG_BLOG;

    $retval = '';

    $title = $LANG_BLOG['pi_title'];
    if ($subject != '')
        $title = $subject . ' : ' . $title;

    switch($_BLOG_CONF['displayblocks']) {
    case 2:     // right only
    case 0:     // none
        $retval .= COM_siteHeader('none', $title, $meta);
        break;

    case 1:     // left only
    case 3:     // both
    default :
        $retval .= COM_siteHeader('menu', $title, $meta);
        break;
    }

    return $retval;

}


/**
*   Show the site footer, with or without right blocks according to config.
*
*   @see    COM_siteFooter()
*   @return string              HTML for site header
*/
function BLOG_siteFooter()
{
    global $_BLOG_CONF;

    $retval = '';

    switch($_BLOG_CONF['displayblocks']) {
    case 0: // none
    case 1: // left only
        $retval .= COM_siteFooter();
        break;

    case 2 : // right only
    case 3 : // left and right
    default:
        $retval .= COM_siteFooter(true);
        break;
    }

    return $retval;

}

?>
