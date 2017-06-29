<?php
//  $Id: import.php 19 2011-04-04 17:15:51Z root $
/**
*   Utility to import Stories into Blog Items.
*   This will need to be incorporated into the plugin installation or 
*   invoked as a standalone function.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

require_once '../../../public_html/lib-common.php';

$sql = array(
// Get all non-draft stories into the blog_items table
"INSERT INTO {$_TABLES['blogs']} (
    sid, author_id, draft_flag, tid, date, title, introtext,
    bodytext, hits, rating, votes, numemails, comments, comment_expire,
    trackbacks, related, featured, show_topic_icon, commentcode,
    trackbackcode, statuscode, expire, postmode, advanced_editor_mode,
    frontpage, owner_id, group_id, perm_owner, perm_group, perm_members,
    perm_anon
) SELECT
    sid, uid, draft_flag, tid, date, title, introtext,
    bodytext, hits, rating, votes, numemails, comments, comment_expire,
    trackbacks, related, featured, show_topic_icon, commentcode,
    trackbackcode, statuscode, expire, postmode, advanced_editor_mode,
    frontpage, owner_id, group_id, perm_owner, perm_group, perm_members,
    perm_anon
FROM {$_TABLES['stories']}",
//WHERE draft_flag = 0",

// Mark all stories as draft to disable them
"UPDATE {$_TABLES['stories']} SET draft_flag = 1",

// Copy comments from stories to blogs
"INSERT INTO {$_TABLES['comments']} (
    cid, type, sid, date, title, comment, score, reason, pid, lft, rht,
    indent, name, uid, ipaddress
) SELECT
     0, 'blog', sid, date, title, comment, score, reason, pid, lft, rht,
    indent, name, uid, ipaddress
FROM {$_TABLES['comments']}
WHERE type='article'",

);

foreach ($sql as $s) {
    DB_query($s);
    if (DB_error()) break;
}

?>
