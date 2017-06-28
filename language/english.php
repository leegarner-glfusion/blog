<?php
//  $Id: english.php 13 2011-03-25 21:26:59Z root $
/**
*   English UTF-8 Language file for the Blog plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

$LANG_BLOG = array(
    'pi_title'  => 'Blogs',
    'blog_admin' => 'Blog Administration',
    'blog_editor' => 'Blog Editor',
    'blog_moderate' => 'Moderate Blog',
    'commentmode'   => 'Comments',
    'no_js' => 'JavaScript needs to be enabled for Advanced Editor. Option can be disabled in the Configuration admin panel.',
    'no_js_link'  => 'Click <a href="%s?mode=edit&amp;sid=%s&amp;editopt=default">here</a> to use default editor',

    'newpage' => 'New Page',
    'adminhome' => 'Admin Home',
    'blogs' => 'Blogs',
    'blogeditor' => 'Blog Editor',
    'writtenby' => 'Author',
    'date' => 'Date',
    'publishdate' => 'Publication Date',
    'publishoptions' => 'Publication Options',
    'content' => 'Content',
    'hits' => 'Views',
    'emails' => 'Emails',
    'comments' => 'Comments',
    'show_topic_icon' => 'Show Topic Icon',
    'draft' => 'Draft',
    'postmode' => 'Post Mode',
    'introtext' => 'Introduction',
    'bodytext' => 'Body Text',
    'bloglist' => 'Blog List',
    'expandhelp' => 'Expand the Content Edit Area size',
    'reducehelp' => 'Reduce the Content Edit Area size',
    'featured'  => 'Featured',
    'toolbar' => 'Toolbar Selection',
    'cmt_disable' => 'Auto-close Comments',
    'enabled' => 'Enabled',
    'archivetitle' => 'Archive Options',
    'images'    => 'Images',
    'option' => 'Option',
    'optionarchive' => 'Auto Archive',
    'optiondelete' => 'Auto Delete',
    'publish_date_explanation' => '<b>NOTE:</b> if you modify this date to be in the future, this blog will not be published until that date.  That also means the story will not be included in your feed headline feed and it will be ignored by the search and statistics pages.',
    'url' => 'URL',
    'edit' => 'Edit',
    'lastupdated' => 'Last Updated',
    'pageformat' => 'Page Format',
    'leftrightblocks' => 'Left &amp; Right Blocks',
    'blankpage' => 'Blank Page',
    'noblocks' => 'No Blocks',
    'leftblocks' => 'Left Blocks',
    'addtomenu' => 'Add To Menu',
    'label' => 'Label',
    'nopages' => 'No blog entries are in the system yet',
    'save' => 'Save',
    'preview' => 'Preview',
    'delete' => 'Delete',
    'cancel' => 'Cancel',
    'access_denied' => 'Access Denied',
    'access_denied_msg' => 'You are illegally trying access one of the Blog administration pages.  Please note that all attempts to illegally access this page are logged',
    'all_html_allowed' => 'All HTML is allowed',
    'results' => 'Blog Results',
    'author' => 'Author',
    'no_title_or_content' => 'You must at least fill in the <b>Title</b> and <b>Content</b> fields.',
    'no_such_page_anon' => 'Please log in..',
    'no_page_access_msg' => "This could be because you're not logged in, or not a member of {$_CONF['site_name']}. Please <a href=\"{$_CONF['site_url']}/users.php?mode=new\"> become a member</a> of {$_CONF['site_name']} to receive full membership access",
    'php_msg' => 'PHP: ',
    'php_warn' => 'Warning: PHP code in your page will be evaluated if you enable this option. Use with caution !!',
    'exit_msg' => 'Exit Type: ',
    'exit_info' => 'Enable for Login Required Message.  Leave unchecked for normal security check and message.',
    'deny_msg' => 'Access to this page is denied.  Either the page has been moved/removed or you do not have sufficient permissions.',
    'stats_headline' => 'Top Ten Blogs',
    'stats_page_title' => 'Page Title',
    'stats_hits' => 'Hits',
    'stats_no_hits' => 'It appears that there are no blog entries on this site or no one has ever viewed them.',
    'stats' => 'Blog Stats',
    'id' => 'ID',
    'duplicate_id' => 'The ID you chose for this blog entry is already in use. Please select another ID.',
    'instructions' => 'To modify or delete a blog entry, click on that page\'s edit icon below. To view a blog entry, click on the title of the page you wish to view. To create a new blog entry, click on "Create New" above. Click on on the copy icon to create a copy of an existing page.',
    'centerblock' => 'Centerblock: ',
    'centerblock_msg' => 'When checked, this blog entry will be displayed as a center block on the index page.',
    'position' => 'Position: ',
    'all_topics' => 'All',
    'no_topic' => 'Homepage Only',
    'position_top' => 'Top Of Page',
    'position_feat' => 'After Featured Story',
    'position_bottom' => 'Bottom Of Page',
    'position_entire' => 'Entire Page',
    'position_nonews' => 'Only if No Other News',
    'head_centerblock' => 'Centerblock',
    'centerblock_no' => 'No',
    'centerblock_top' => 'Top',
    'centerblock_feat' => 'Feat. Story',
    'centerblock_bottom' => 'Bottom',
    'centerblock_entire' => 'Entire Page',
    'centerblock_nonews' => 'If No News',
    'inblock_msg' => 'In a block: ',
    'inblock_info' => 'Wrap Blog in a block.',
    'title_edit' => 'Edit page',
    'title_copy' => 'Make a copy of this page',
    'title_display' => 'Display page',
    'select_php_none' => 'do not execute PHP',
    'select_php_return' => 'execute PHP (return)',
    'select_php_free' => 'execute PHP',
    'php_not_activated' => "The use of PHP in blog entries is not activated. Please see the <a href=\"{$_CONF['site_url']}/docs/blog.html#php\">documentation</a> for details.",
    'printable_format' => 'Printable Format',
    'copy' => 'Copy',
    'limit_results' => 'Limit Results',
    'search' => 'Make Searchable',
    'submit' => 'Submit',
    'delete_confirm' => 'Are you sure you want to delete this page?',
    'allnhp_topics' => 'All Topics (No Homepage)',
);

$PLG_blog_MESSAGE19 = '';
$PLG_blog_MESSAGE20 = '';

// Messages for the plugin upgrade
$PLG_blog_MESSAGE3001 = 'Plugin upgrade not supported.';
$PLG_blog_MESSAGE3002 = $LANG32[9];

// Localization of the Admin Configuration UI
$LANG_configsections['blog'] = array(
    'label' => 'Blog',
    'title' => 'Blog Configuration'
);

$LANG_confignames['blog'] = array(
    'allow_php' => 'Allow PHP',
    'sort_by' => 'Sort Centerblocks By',
    'sort_menu_by' => 'Sort Menu Entries By',
    'delete_pages' => 'Delete Pages with Owner',
    'in_block' => 'Wrap Pages in Block',
    'show_hits' => 'Show Hits',
    'show_date' => 'Show Date',
    'filter_html' => 'Filter HTML',
    'censor' => 'Censor Content',
    'default_permissions' => 'Page Default Permissions',
    'aftersave' => 'After Saving Page',
    'atom_max_items' => 'Max. Pages in Web Services Feed',
    'comment_code' => 'Comment Default',
    'include_search' => 'Site Search Default',
    'status_flag' => 'Default Page Mode',
);

$LANG_configsubgroups['blog'] = array(
    'sg_main' => 'Main Settings'
);

$LANG_fs['blog'] = array(
    'fs_main' => 'Blog Main Settings',
    'fs_permissions' => 'Default Permissions'
);

// Note: entries 0, 1, 9, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['blog'] = array(
    0 => array('True' => 1, 'False' => 0),
    1 => array('True' => true, 'False' => false),
    2 => array('Date' => 'date', 'Page ID' => 'id', 'Title' => 'title'),
    3 => array('Date' => 'date', 'Page ID' => 'id', 'Title' => 'title', 'Label' => 'label'),
    9 => array('Forward to page' => 'item', 'Display List' => 'list', 'Display Home' => 'home', 'Display Admin' => 'admin'),
    12 => array('No access' => 0, 'Read-Only' => 2, 'Read-Write' => 3),
    13 => array('Enabled' => 1, 'Disabled' => 0 ),
    17 => array('Comments Enabled' => 0, 'Comments Disabled' => -1),
);

?>
