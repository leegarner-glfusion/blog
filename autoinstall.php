<?php
//  $Id: autoinstall.php 18 2011-04-04 15:59:00Z root $
/**
*   Provides automatic installation of the Blog plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_dbms;
global $LANG_BLOG;

$pi_dir = dirname(__FILE__);
require_once $pi_dir . '/functions.inc';
require_once $pi_dir . '/sql/'. $_DB_dbms. '_install.php';

//  Plugin installation options
$INSTALL_plugin['blog'] = array(
    'installer' => array('type' => 'installer', 
            'version' => '1', 
            'mode' => 'install'),

    'plugin' => array('type' => 'plugin', 
            'name'      => $_BLOG_CONF['pi_name'],
            'ver'       => $_BLOG_CONF['pi_version'], 
            'gl_ver'    => $_BLOG_CONF['gl_version'],
            'url'       => $_BLOG_CONF['pi_url'], 
            'display'   => $_BLOG_CONF['pi_display_name']),

    array('type' => 'table', 
            'table'     => $_TABLES['blogs'], 
            'sql'       => $_SQL['blogs']),

    array('type' => 'table', 
            'table'     => $_TABLES['blogsubmission'], 
            'sql'       => $_SQL['blog_submission']),

    array('type' => 'group', 
            'group' => 'blog Admin', 
            'desc' => 'Users in this group can administer the Blog plugin',
            'variable' => 'admin_group_id', 
            'addroot' => true),

    array('type' => 'feature', 
            'feature' => 'blog.admin', 
            'desc' => 'Blog Administrator',
            'variable' => 'admin_feature_id'),

    array('type' => 'feature', 
            'feature' => 'blog.edit', 
            'desc' => 'Blog Editor',
            'variable' => 'edit_feature_id'),

    array('type' => 'feature', 
            'feature' => 'blog.submit', 
            'desc' => 'Blog Submit',
            'variable' => 'submit_feature_id'),

    array('type' => 'feature', 
            'feature' => 'blog.moderate', 
            'desc' => 'Blog Submit',
            'variable' => 'moderate_feature_id'),

    array('type' => 'mapping', 
            'group' => 'admin_group_id', 
            'feature' => 'admin_feature_id',
            'log' => 'Adding Admin feature to the admin group'),

    array('type' => 'mapping', 
            'group' => 'admin_group_id', 
            'feature' => 'edit_feature_id',
            'log' => 'Adding Edit feature to the admin group'),

    array('type' => 'mapping', 
            'group' => 'admin_group_id', 
            'feature' => 'submit_feature_id',
            'log' => 'Adding Submit feature to the admin group'),

    array('type' => 'mapping', 
            'group' => 'admin_group_id', 
            'feature' => 'moderate_feature_id',
            'log' => 'Adding Moderate feature to the admin group'),

);
    
 

/**
*   Puts the datastructures for this plugin into the glFusion database
*   Note: Corresponding uninstall routine is in functions.inc
*
*   @return   boolean True if successful False otherwise
*/
function plugin_install_blog()
{
    global $INSTALL_plugin, $_BLOG_CONF;

    $pi_display_name    = $_BLOG_CONF['pi_display_name'];
    COM_errorLog("Attempting to install the $pi_display_name plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$_BLOG_CONF['pi_name']]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
*   Loads the configuration records for the Online Config Manager
*
*   @return boolean     True = success, False = an error occured
*/
function plugin_load_configuration_blog()
{
    global $_CONF, $_BLOG_CONF, $_TABLES, $pi_dir;

    require_once $pi_dir . '/install_defaults.php';

    // Get the admin group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id', 
            "grp_name='{$_BLOG_CONF['pi_name']} Admin'");

    return plugin_initconfig_blog($group_id);
}


function plugin_postinstall_blog()
{
    global $_TABLES;

    DB_query("INSERT INTO {$_TABLES['vars']} VALUES ('blog_lastemail', '0')",1);
    return true;
}



?>
