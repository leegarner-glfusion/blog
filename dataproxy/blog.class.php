<?php
//  $Id: blog.class.php 18 2011-04-04 15:59:00Z root $
/**
*   Dataproxy driver for the Blog plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

class Dataproxy_blog extends DataproxyDriver
{
    var $driver_name = 'blog';

    /**
    *   Get all the topics (categories) under the given topic ID.
    *   Since topics have no hierarchy, $pid must be false
    *
    *   @param  mixed   $pid        Parent ID
    *   @param  boolean $all_langs
    *   @return array           Array of topics
    */
    function getChildCategories($pid = false, $all_langs = false)
    {
        global $_BLOG_CONF, $_TABLES;

        $retval = array();
        if ($pid !== false) {
            // Topics have no hierarchy
            return $retval;
        }

        $sql = "SELECT tid, topic, imageurl 
                FROM {$_TABLES['topics']} 
                WHERE (1=1) ";

        // If the current user is logged-in, exclude the topics that they
        // don't want to see
        if ($this->uid > 1) {
            $tids = DB_getItem(
                $_TABLES['userindex'], 'tids', "uid = '" . $this->uid . "'"
            );
            if (!empty($tids)) {
                $sql .= " AND (tid NOT IN ('"
                     . str_replace(' ', "','", addslashes($tids)) . "'))";
            }
        }

        // Adds permission check.  When uid is 0, then it means access as Root
        if ($this->uid > 0) {
            $sql .= COM_getPermSQL('AND', $this->uid);
        }

        // Adds lang id.  When uid is 0, then it means access as Root
        if ( $this->uid > 0 && function_exists('COM_getLangSQL') &&
                $all_langs === false) {
            $sql .= COM_getLangSQL('tid', 'AND');
        }

        if ($_CONF['sortmethod'] == 'alpha') {
            $sql .= ' ORDER BY topic ASC';
        } else {
            $sql .= ' ORDER BY sortnum';
        }
        $result = DB_query($sql, 1);
        if (DB_error()) {
            return $retval;
        }

        while ($A = DB_fetchArray($result, false)) {
            $retval[] = array(
                'id'        => $A['tid'],
                'title'     => $A['topic'],
                'uri'       => $_CONF['site_url'] . '/index.php?topic=' . 
                                urlencode($A['id']),
                'date'      => false,
                'image_uri' => $A['imageurl'],
            );
        }

        return $retval;
    }


    /**
    *   Get a single item by its ID
    *
    *   @param  boolean $all_langs  Search all languages, or not
    *   @return array               Array of item information
    */
    function getItemById($id, $all_langs = false)
    {
        global $_BLOG_CONF, $_TABLES;

        $retval = array();
    
        $sql = "SELECT * 
                FROM {$_TABLES['blogs']} 
                WHERE (sid ='" . DB_escapeString($id) . "') 
                AND (draft_flag = 0) 
                AND (date <= '{$_BLOG_CONF['today']}')) ";

        // If this user isn't Root, check the topic and article permissions
        if ($this->uid > 0) {
            $sql .= COM_getTopicSql('AND', $this->uid);
            $sql .= COM_getPermSql('AND', $this->uid);
            if ($all_langs === false && function_exists('COM_getLangSQL')) {
                $sql .= COM_getLangSQL('sid', 'AND');
            }
        }
        $result = DB_query($sql, 1);
        if (DB_error()) {
            return $retval;
        }

        if (DB_numRows($result) == 1) {
            $A = DB_fetchArray($result, false);

            $retval['id']        = $id;
            $retval['title']     = $A['title'];
            $retval['uri']       = COM_buildUrl(BLOG_URL . 
                                    '/index.php?sid=' . urlencode($id));
            $retval['date']      = strtotime($A['date']);
            $retval['image_uri'] = false;
            $retval['raw_data']  = $A;
        }

        return $retval;
    }


    /**
    *   Get all items under the specified topic
    *
    *   @param  string  $tid        Topic ID
    *   @param  boolean $all_langs  Search for all languages, or not
    *   @return array               Array of items
    */
    function getItems($tid, $all_langs = false)
    {
        global $_CONF, $_TABLES, $_BLOG_CONF;

        $retval = array();

        $sql = "SELECT sid, title, UNIX_TIMESTAMP(date) AS day 
                FROM {$_TABLES['blogs']} 
                WHERE (draft_flag = 0) 
                AND (date <= '{$_BLOG_CONF['today']}')
                AND (tid = '" . DB_escapeString($tid) . "') ";

        // If not Root, check topic and article permissions and language
        if ($this->uid > 0) {
            $sql .= COM_getTopicSql('AND', $this->uid)
                 .  COM_getPermSql('AND', $this->uid);
            if ($all_langs === false && function_exists('COM_getLangSQL')) {
                $sql .= COM_getLangSQL('sid', 'AND');
            }
        }
        $sql .= ' ORDER BY date DESC';
        $result = DB_query($sql);
        if (DB_error()) {
            return $retval;
        }

        while (($A = DB_fetchArray($result, false)) !== false) {
            $retval[] = array(
                'id'    => $A['sid'],
                'title' => $A['title'],
                'uri'   => COM_buildUrl(BLOG_URL . 
                            '/index.php?sid=' . urlencode($A['id'])),
                'date'  => $A['day'],
                'imageurl' => false,
            );
        }

        return $retval;
    }
}
?>
