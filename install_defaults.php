<?php
//  $Id: install_defaults.php 17 2011-04-01 16:39:33Z root $
/**
*   Install default configuration values for the Blog plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    article
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
if (!defined ('GVERSION')) {
    die('This file can not be used on its own!');
}

/*
*   Blog default settings.
*
*   Initial Installation Defaults used when loading the online configuration
*   records. These settings are only used during the initial installation
*   and not referenced any more once the plugin is installed
*/

// TODO: verify that these are needed:
//  $_BLOG_DEFAULT['allow_php'] = 1;
//  $_BLOG_DEFAULT['hidenewblog'] = 2;         

global $_BLOG_DEFAULT;
$_BLOG_DEFAULT = array(
    // Front-page sort options
    'fpsortby'      => 'date',      // can be 'id', 'title', 'date'
    'fpsortdir'     => 'desc',      // can be 'desc' or 'asc'

    'notification'  => 0,
    'submission'    => 1,           // use submission queue?
    'newbloginterval' => 14,        // number of days for whatnew list
    'perpage'       => 10,          // number of entries per page
    'adveditor'     => false,       // allow advanced editor?
    'displayblocks' => 3,           // blocks to display (left, right, etc)
    'maximages'     => 5,           // max image attachments per entry
    'showfirstasfeatured' => 0,     // treat first entry as featured?
    'emailstoryloginrequired' => 1, // login required to send email?
    'loginrequired' => 0,           // login required to view?
    'hideblogmenu'  => 0,           // hide from plugins menu?
    'delete_pages'  => 0,           // delete entries with user?
    'show_hits'     => 1,           // Show hit counts? 1=yes, 0=no
    'show_date'     => 1,           // Show date with article?

    // If you experience timeout issues, you may need to set both of the
    // following values to 0 as they are intensive
    // NOTE: using filter_html will render any blank pages useless
    'filter_html'   => 0,
    'censor'        => 1,

    'default_permissions' => array(3, 2, 2, 2),
    'def_group'     => 13,          // Default value for "group"

    'atom_max_items' => 10,         // Max items shown in Atom feed
    'include_search' => 1,          // Include articles in system search?
    'comment_code'  => -1,          // Enable (0) or disable (-1) comments
    'status_flag'   => 1,           // Default status, enabled=1
);


/**
*   Initialize configuration
*
*   @return   boolean     true: success; false: an error occurred
*/
function plugin_initconfig_blog()
{
    global $_BLOG_DEFAULT;

    $c = config::get_instance();
    if (!$c->group_exists('article')) {

        $c->add('sg_main', NULL, 'subgroup',
                0, 0, NULL, 0, true, 'article');
        $c->add('fs_main', NULL, 'fieldset',
                0, 0, NULL, 0, true, 'article');
        $c->add('allow_php', $_BLOG_DEFAULT['allow_php'], 'select',
                0, 0, 0, 10, true, 'article');
        $c->add('sort_by', $_BLOG_DEFAULT['sort_by'], 'select',
                0, 0, 2, 20, true, 'article');
        $c->add('sort_menu_by', $_BLOG_DEFAULT['sort_menu_by'], 'select',
                0, 0, 3, 30, true, 'article');
        $c->add('delete_pages', $_BLOG_DEFAULT['delete_pages'], 'select',
                0, 0, 0, 40, true, 'article');
        $c->add('in_block', $_BLOG_DEFAULT['in_block'], 'select',
                0, 0, 0, 50, true, 'article');
        $c->add('show_hits', $_BLOG_DEFAULT['show_hits'], 'select',
                0, 0, 0, 60, true, 'article');
        $c->add('show_date', $_BLOG_DEFAULT['show_date'], 'select',
                0, 0, 0, 70, true, 'article');
        $c->add('filter_html', $_BLOG_DEFAULT['filter_html'], 'select',
                0, 0, 0, 80, true, 'article');
        $c->add('censor', $_BLOG_DEFAULT['censor'], 'select',
                0, 0, 0, 90, true, 'article');
        $c->add('include_search', $_BLOG_DEFAULT['include_search'], 'select',
                0, 0, 0, 100, true, 'article');
        $c->add('comment_code', $_BLOG_DEFAULT['comment_code'], 'select',
                0, 0,17, 110, true, 'article');
        $c->add('status_flag', $_BLOG_DEFAULT['status_flag'], 'select',
                0, 0, 13,120, true, 'article');
        $c->add('aftersave', $_BLOG_DEFAULT['aftersave'], 'select',
                0, 0, 9, 130, true, 'article');
        $c->add('atom_max_items', $_BLOG_DEFAULT['atom_max_items'], 'text',
                0, 0, null, 140, true, 'article');

        $c->add('fs_permissions', NULL, 'fieldset',
                0, 1, NULL, 0, true, 'article');
        $c->add('default_permissions', $_BLOG_DEFAULT['default_permissions'],
                '@select', 0, 1, 12, 150, true, 'article');
    }

    return true;
}

?>
