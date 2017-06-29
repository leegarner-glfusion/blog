<?php
//  $Id: BlogItem.class.php 19 2011-04-04 17:15:51Z root $
/**
*   @author     Lee P. Garner <lee AT leegarner DOT com>
*   @package    blog
*   @version    0.0.1
*   @copyright  Copyright &copy; 2011 Lee P. Garner
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU General Public License v2
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

USES_blog_class_blog();

/**
*   @package blog
*/
class BlogItem extends Blog
{

    var $_dtObject, $_cmtClose, $_dtExpire;   // Date object holders

    /**
     * Constructor, creates a story, taking a (glFusion) database object.
     * @param $mode   string    Story class mode, either 'admin' or 'submission'
     */
    function __construct($sid = '', $mode = 'admin')
    {
        global $_USER, $_BLOG_CONF, $_CONF;

        // Call the Blog::__construct function.  This calls $this->setDefaults()
        // to set default values.
        parent::__construct($sid, $mode);

        if ($sid != '') {
            if (!$this->Read($sid, $mode)) {
                // Tried to load a non-existent blog.  Clear the sid
                // and set the error code for the caller to check
                $this->sid = '';
                $this->error = 1;
                $this->originalSid = $this->sid;
            } else {
                // Successful read
                $this->isNew = false;
                $this->_cmtClose = new Date($this->comment_expire, 
                                    $_CONF['timezone']);
            }
        } else {
            $this->sid = COM_makeSid();
            $this->_cmtClose = new Date($this->date + 
                ($_BLOG_CONF['cmt_close_days'] * 86400), $_CONF['timezone']);
        }

        $this->_dtObject = new Date($this->date, $_CONF['timezone']);
        $this->_dtExpire = new Date($this->expire, $_CONF['timezone']);

    }


    /**
    *   Set default values for the fields.
    *   This makes sure that proper defaults are set for cases where
    *   some fields may not be supplied, such as when approving a submission.
    */
    function setDefaults()
    {
        global $_CONF, $_BLOG_CONF, $_USER;

        $this->perm_owner = (int)$_BLOG_CONF['default_permissions'][0];
        $this->perm_group = (int)$_BLOG_CONF['default_permissions'][1];
        $this->perm_members = (int)$_BLOG_CONF['default_permissions'][2];
        $this->perm_anon = (int)$_BLOG_CONF['default_permissions'][3];
        $this->hits = 0;
        $this->rating = 0;
        $this->votes = 0;
        $this->numemails = 0;
        $this->comments = 0;
        $this->trackbacks = 0;
        $this->related = 0;
        $this->show_topic_icon = 1;
        $this->draft_flag = 0;
        $this->frontpage = 0;
        $this->featured = 0;
        $this->sid = '';
        $this->owner_id = $_USER['uid'];
        $this->author_id = $this->owner_id;
        $this->group_id = $_BLOG_CONF['default_group'];
        $this->tid = '';
        $this->title = '';
        $this->introtext = '';
        $this->bodytext = '';
        $this->date = date('Y-m-d H:i:s');
        $this->archiveflag = 0;

        // We always set the comment expiration to something.  If the
        // cmt_close_flag is unset then the date will revert to zeros
        $this->comment_expire = $this->AddDays($_BLOG_CONF['cmt_close_days']);
        $this->cmt_close_flag = (int)$_BLOG_CONF['cmt_close_flag'];

        if ($_CONF['advanced_editor'] != 1) $_BLOG_CONF['adveditor'] = 0;
        $this->postmode = $_BLOG_CONF['adveditor'] == 2 ? 'adveditor' : 'html';
    }

    /**
    *   Read an blog from the database
    *
    *   @param  string  $sid    Optional ID, current object ID used if empty
    *   @return boolean         True on success, False on failure (no such ID)
    */
    function Read($sid='', $mode='')
    {
        global $_TABLES;

        if ($sid != '') {
            $this->sid = $sid;
            $perms = $this->isAdmin ? '' : COM_getPermSQL('AND');
        }

        $sql = "SELECT *, UNIX_TIMESTAMP(date) AS ux_date
                FROM {$_TABLES['blogs']}
                WHERE sid = '{$this->sid}' " . 
                $perms;
        //echo $sql;die;
        $result = DB_query($sql);
        $count = DB_numRows($result);

        if ($count != 1) {
            return false;
        }

        $A = DB_fetchArray($result, false);
        $this->setVars($A, true);
        return true;
    }


    /**
    *   Set the blog variables from the supplied array.
    *   The array may be from a form ($_POST) or database record.
    *
    *   @param  array   $A          Array of values
    *   @param  boolean $fromDB     True if reading from DB, false if from form
    */
    function setVars($A='', $fromDB=false)
    {
        global $_BLOG_CONF, $_CONF, $_USER;

        if (!is_array($A))
            return;

        if ($fromDB) {
            // Coming from the database.  Load all the values that are stored
            // differently in the database, or only exist there
            $this->perm_owner = (int)$A['perm_owner'];
            $this->perm_group = (int)$A['perm_group'];
            $this->perm_members = (int)$A['perm_members'];
            $this->perm_anon = (int)$A['perm_anon'];

            foreach (array('author_id', 'hits', 'rating', 'votes', 'numemails',
                        'comments', 'trackbacks', 'related', 'show_topic_icon',
                        'draft_flag', 'statuscode',
                        'date', 'ux_date', 'comment_expire',
                        'perm_owner', 'perm_group', 'perm_members', 'perm_anon',
                        'owner_id', 'group_id',
                    ) as $name) {
                if (isset($A[$name])) $this->$name = $A[$name];
            }
            if ($A['comment_expire'] > '0000-00-00 00:00:00') {
                $this->cmt_close_flag = 1;
            } else {
                $this->cmt_close_flag = 0;
            }

        } else {

            // This is coming from a form. Some values are seen differently
            // in the $_POST array
            if (is_array($A['perm_owner']) ||
                is_array($A['perm_group']) ||
                is_array($A['perm_members']) ||
                is_array($A['perm_anon']) ) {
                list($this->perm_owner, $this->perm_group,
                    $this->perm_members,$this->perm_anon) = 
                    SEC_getPermissionValues($A['perm_owner'],$A['perm_group'],
                    $A['perm_members'], $A['perm_anon']);
            } else {
                // no permissions given, use defaults
                list($this->perm_owner, $this->perm_group,
                    $this->perm_members,$this->perm_anon) = 
                    $_BLOG_CONF['default_permissions'];
            }

            // checkboxes, 1 if set, 0 if missing
            $this->draft_flag = isset($A['draft_flag']) ? 1 : 0;
            $this->show_topic_icon = isset($A['show_topic_icon']) ? 1 : 0;
            $this->advanced_editor_mode = isset($A['advanced_editor_mode']) ?
                    1 : 0;
            //$this->frontpage = isset($A['frontpage']) ? 1 : 0;

            // user and group id's.  Current user and default group if empty
            if (empty($A['owner_id'])) $A['owner_id'] = $_USER['uid'];
            if (empty($A['author_id'])) $A['author_id'] = $A['owner_id'];
            if (empty($A['group_id']))
                $A['group_id'] = SEC_getFeatureGroup('blog.edit', $_USER['uid']);
            if (empty($A['postmode'])) {
                $A['postmode'] = $_CONF['postmode'];
                if (!empty($A['content_type'])) {
                    if ($A['content_type'] == 'text') {
                        $A['postmode'] = 'text';
                    } elseif ($A['content_type'] == 'html' || 
                            $A['content_type'] == 'xhtml') {
                            $args['postmode'] = 'html';
                    }
                }
            }

            if (empty($A['tid']))
                $A['tid'] = $this->getTopic();

            // Create the dates from components
            $this->date = $this->_makeDate($A, 'publish');
            if (isset($A['archiveflag'])) {
                $this->expire = $this->_makeDate($A, 'expire');
                $this->archiveflag = 1;
            } else {
                $this->expire = '0000-00-00 00:00:00';
                $this->archiveflag = 0;
            }

            $this->statuscode= isset($A['statuscode']) ? 
                    (int)$A['statuscode'] : 0;

            if (isset($A['cmt_close_flag'])) {
                $this->comment_expire = $this->_makeDate($A, 'cmt_close');
                $this->cmt_close_flag = 1;
            } else {
                $this->comment_expire = '0000-00-00 00:00:00';
                $this->cmt_close_flag = 0;
            }

            /*$this->statuscode = $A['statuscode'];
            $this->featured = $A['featured'];
            $this->trackbackcode = $A['trackbackcode'];
            $this->commentcode = $A['commentcode'];*/

            if (isset($A['sid']) && !empty($A['sid'])) {
                $this->sid = $A['sid'];
            }
        }

        // The rest of the values are the same whether in a form or the DB
        foreach (array(
                    'advanced_editor_mode', 'frontpage',
                    'tid', 'title', 'introtext', 'bodytext',
                    'featured', 'trackbackcode',
                    'commentcode', 'postmode',
                ) as $name) {
            if (isset($A[$name])) $this->$name = $A[$name];
        }

    }


    /**
    *   Magic function to set individual properties.
    *   Basic sanitization happens here, but they're not escaped for the DB.
    *   Only known properties are saved in the $properties array.
    *
    *   @param  string  $key    Name of the property
    *   @param  mixed   $value  Value to store
    */
    function __set($key, $value)
    {
        switch ($key) {
        case 'sid':
        case 'originalSid':
            // Blog ID value
            $this->properties[$key] = COM_sanitizeID($value);
            break;

        case 'author_id':
        case 'hits':
        case 'votes':
        case 'numemails':
        case 'comments':
        case 'trackbacks':
        case 'statuscode':
        case 'featured':
        case 'commentcode':
        case 'trackbackcode':
        case 'advanced_editor_mode':
        case 'frontpage':
        case 'owner_id':
        case 'group_id':
        case 'perm_owner':
        case 'perm_group':
        case 'perm_members':
        case 'perm_anon':
        case 'error':
        case 'ux_date':
            // Integer values
            $this->properties[$key] = (int)$value;
            break;

        case 'draft_flag':
        case 'show_topic_icon':
        case 'cmt_close_flag':
        case 'archiveflag':
            // Boolean values
            $this->properties[$key] = $value == 1 ? 1 : 0;
            break;

        case 'rating':
            // Floating-point values
            $this->properties[$key] = (float)$value;
            break;

        case 'tid':
        case 'date':
        case 'title':
        case 'introtext':
        case 'bodytext':
        case 'comment_expire':
        case 'related':
        case 'expire':
        case 'postmode':
            // Simple string values
            $this->properties[$key] = trim($value);
            break;
        }

    }


    /**
    *   Update the hit counter for this blog.
    *   Makes sure to only update the counter for non-draft, published blogs.
    */
    function UpdateHits()
    {
        global $_TABLES;

        DB_query("UPDATE {$_TABLES['blogs']} 
            SET hits = hits + 1 
            WHERE (sid = '" . DB_escapeString($this->sid) . "') 
            AND (date <= NOW()) 
            AND (draft_flag = 0)");
    }


    /**
    *   Create "What's Related" links for a story
    *
    *   Creates an HTML-formatted list of links to be used for the What's 
    *   Related block next to a story (in blog view).
    *
    *   @return   string              HTML-formatted list of links
    */
    function WhatsRelated()
    {
        global $_CONF, $_TABLES, $_USER, $LANG24, $_BLOG_CONF;

        // get the links from the story text
        $related = $this->related;
        if ($this->related != '') {
            $rel = explode ("\n", $this->related);
        } else {
            $rel = array ();
        }

        if (!COM_isAnonUser() || ($_CONF['loginrequired'] == 0 &&
                $_CONF['searchloginrequired'] == 0)) {
            $srchBaseUrl = $_CONF['site_url'] . 
                '/search.php?mode=search&amp;type=' . $_BLOG_CONF['pi_name'];
            // add a link to "search by author"
            if ($_CONF['contributedbyline'] == 1) {
                $author = COM_getDisplayName($this->author_id);
                $rel[] = '<a href="' . $srchBaseUrl . '&amp;author=' .
                    $this->author_id . '">' . $LANG24[37] . ' ' . $author . 
                    '</a>';
            }

            // add a link to "search by topic"
            $topic = DB_getItem($_TABLES['topics'], 'topic', 
                        "tid = '".DB_escapeString($this->tid)."'" );
            $rel[] = '<a href="' . $srchBaseUrl . '&amp;topic=' . 
                $this->tid . '">' . $LANG24[38] . ' ' . $topic . '</a>';
        }

        $related = '';
        if (sizeof($rel) > 0) {
            $related = COM_checkWords(COM_makeList($rel, 'list-whats-related'));
        }

        return ($related);
    }


    /**
    *   Check the current user's access level.
    *   If a required access level is provided, then return true or false
    *   depending on whether the user has at least that level.
    *   If no requirement provided, return the actual access level of the user.
    *
    *   @param  integer $req    Minimum access level (optional)
    *   @return mixed           Boolean if a user meets the level, or the level
    */
    function checkAccess($req = -1)
    {
        $access = 0;

        if ($this->isEditor || $this->isModerator) {
            // Admin-level editor always has access
            $access = 3;
        } else {
            $access = SEC_hasAccess($this->owner_id, $this->group_id, 
                        $this->perm_owner, $this->perm_group,
                        $this->perm_members, $this->perm_anon);
        }

        if ($req > -1) {
            // Checking the access level
            return $access < $req ? false : true;
        } else {
            // Just retrieving the level
            return $access;
        }
    }


    /**
     * Returns a story formatted for spam check:
     *
     * @return  string Story formatted for spam check.
     */
    function GetSpamCheckFormat()
    {
        return '<h1>' . $this->_title . '</h1><p>' .
            $this->_introtext . '</p><p>' . $this->_bodytext . '</p>';
    }


    /**
    *   Provide access to blog elements for display.
    *
    *   @param  string  $item   Item to fetch.
    *   @return mixed       The clean and ready to use value requested.
    */
    function DisplayElements($item = 'title')
    {
        global $_CONF, $_TABLES;

        $return = '';

        switch (strtolower($item))
        {
        case 'introtext':
            if ($this->postmode == 'plaintext') {
                $return = nl2br($this->_introtext);
            } elseif ($this->postmode == 'wikitext') {
                $return = COM_renderWikiText($this->_editUnescape($this->introtext));
            } else {
                $return = $this->introtext;
            }

            $return = PLG_replaceTags($this->_displayEscape($return));
            break;

        case 'bodytext':
            $bodytext = $this->bodytext;
            if (!empty($bodytext)) {
                if ($this->postmode == 'plaintext') {
                    $return = nl2br($this->bodytext);
                } elseif ($this->postmode == 'wikitext') {
                    $return = COM_renderWikiText(
                            $this->_editUnescape($this->bodytext));
                } else {
                    $return = $this->_displayEscape($this->bodytext);
                }
                $return = PLG_replaceTags($return);
            } else {
                $return = '';
            }
            break;

        case 'title':
            $return = $this->_displayEscape($this->title);
            break;

        case 'shortdate':
            $return = $this->_dtObject->format($_CONF['shortdate'], true);
            break;

        case 'dateonly':
            $return = $this->_dtObject->format($_CONF['dateonly'], true);
            break;

        case 'date':
            $return = $this->_dtObject->format(
                        $this->_dtObject->getuserFormat(), true);
            break;

        case 'unixdate':
            $return = $this->_dtObject->toUnix();
            break;

        case 'hits':
            $return = COM_NumberFormat($this->hits);
            break;
        case 'rating':
            $return = @number_format($this->rating, 2);
            break;

        case 'votes':
            $return = COM_NumberFormat($this->votes);
            break;
        case 'topic':
            $return = htmlspecialchars($this->topic);
            break;

        case 'expire':
            $return = $this->_dtExpire->toUnix();
            break;

        case 'commentcode':
            // check to see if comment_time has passed
            if (    $this->comment_expire != '' && 
                    time() > $this->comment_expire && 
                    $this->commentcode == 0 ) {
                $return = 1;
                //if comment code is not 1, change it to 1
                DB_query("UPDATE {$_TABLES['blogs']} 
                        SET commentcode = '1' 
                        WHERE sid = '{$this->sid}'");
            } else {
                $return = $this->commentcode;
            }
            break;

        default:
            $return = $this->{$item};
            break;
        }

        return $return;
    }


    /**
    *   Render the blog for display or printing
    *
    *   @param  integer $mode   Mode of display (preview, print, compact, full)
    *   @param  string  $query  Optional query text to highlight
    *   @param  string  $template   Optional template name
    *   @return string      HTML for rendered blog
    */
    public function Render($mode, $query = '', $page = 1)
    {
        global $_CONF, $_BLOG_CONF, $_TABLES, $_USER, 
                $LANG01, $LANG05, $LANG11, $LANG_TRB,
                $_IMAGE_TYPE, $_GROUPS, $ratedIds;

        switch ($mode) {
        case BLOG_PRINT:
        case BLOG_EMAIL:
            $template = 'printable';
            break;
        case BLOG_COMPACT:
        case BLOG_FULL:
        default:
            if ($this->archive == 1) {
                $template = 'archive';
            } elseif ($this->featured == 1) {
                $template = 'featured';
            } else {
                $template = 'blog';
            }
            break;
        }
        $blog_filevar = $template;

        // Get the intro and body text formatted for display.
        // Just get the bodytext if it'll be used, to avoid replacing
        // tags and other overhead
        $introtext = $this->DisplayElements('introtext');
        if ($mode & (BLOG_FULL | BLOG_PRINT | BLOG_EMAIL)) {
            $bodytext = $this->DisplayElements('bodytext');
        } else {
            $bodytext = '';
        }

        if (!empty($query)) {
            $introtext = COM_highlightQuery($introtext, $query);
            $bodytext  = COM_highlightQuery($bodytext, $query);
        }

        $T = new Template(BLOG_PI_PATH . '/templates');
        $T -> set_file(array(
            'blog'              => $template . '.thtml',
            //'bodytext'          => 'bodytext.thtml',
            //'featuredblog'      => 'featured.thtml',
            //'featuredbodytext'  => 'featuredbody.thtml',
            //'archiveblog'       => 'archive.thtml',
            //'archivebodytext'   => 'archivebody.thtml'
            ) );

        if ($_CONF['hidestorydate'] != 1) {
            $T->set_var('story_date', $this->DisplayElements('date'),
                false, true);
        }
        $blogUrl = COM_buildURL(BLOG_URL . '/index.php?sid=' . $this->sid);

        $T->set_var(array(
            'blog_id'        => $this->sid,
            'blog_url'       => $blogUrl,
            'blog_title'     => $this->DisplayElements('title'),
        ) );

        // begin instance caching...
        /*if ($this->DisplayElements('featured') == 1) {
            $blog_filevar = 'featuredblog';
        } elseif ($this->statuscode == STORY_ARCHIVE_ON_EXPIRE && 
                $this->expire <= time() ) {
            $blog_filevar = 'archiveblog';
        } else {
            $blog_filevar = 'blog';
        }*/

        $hash = CACHE_security_hash();
        $instance_id = "blog_{$this->sid}_{$index}{$mode}'_{$blog_filevar}_{$hash}_{$_CONF['theme']}";

        if ($_CONF['contributedbyline'] == 1) {
            $fullname = $this->DisplayElements('fullname');
            $username = $this->DisplayElements('username');
            $T->set_var('contributedby_user', $username);
            if (empty($fullname)) {
                $T->set_var('contributedby_fullname', $username);
            } else {
                $T->set_var('contributedby_fullname',$fullname);
            }

            $authorname = COM_getDisplayName($this->author_id, 
                            $username, $fullname);
            $T->set_var('author', $authorname);

            if ($this->author_id> 1) {
                $profileUrl = $_CONF['site_url'] . 
                        '/users.php?mode=profile&amp;uid=' . $this->author_id;
                $T->set_var('contributedby_url', $profileUrl);
                $authorname = COM_createLink($authorname, 
                        $profileUrl, 
                        array('class' => 'storybyline'));
            }
            $T->set_var('contributedby_author', $authorname);

            $photo = '';
            if ($_CONF['allow_user_photo'] == 1) {
                USES_lib_user();
                $authphoto = $this->photo;
                if (empty($authphoto)) {
                    $authphoto = '(none)'; // user does not have a photo
                }
                $photo = USER_getPhoto($this->author_id, $authphoto,
                                       $this->DisplayElements('email'));
            }
            if (!empty($photo)) {
                $camera_icon = '<img src="' . $_CONF['layout_url']
                        . '/images/smallcamera.' . $_IMAGE_TYPE . '" alt=""'
                        . XHTML . '>';
                $T->set_var(array(
                        'contributedby_photo'   => $photo,
                        'author_photo'          => $photo,
                        'camera_icon' => 
                                COM_createLink($camera_icon, $profileUrl),
                ) );
            } else {
                $T->set_var(array(
                    'contributedby_photo'   => '',
                    'author_photo'          => '',
                    'camera_icon'           => '',
                ) );
            }
        }

        $tid = $this->DisplayElements('tid');
        $topicname = $this->DisplayElements('topic');
        $T->set_var('story_topic_id', $tid);
        $T->set_var('story_topic_name', $topicname);

        $topicurl = $_CONF['site_url'] . '/index.php?topic=' . $tid;
        if ( (!isset($_USER['noicons']) || $_USER['noicons'] != 1) &&
                $this->show_topic_icon == 1) {
            $imageurl = $this->DisplayElements('imageurl');
            if(!empty($imageurl)) {
                $imageurl = COM_getTopicImageUrl($imageurl);
                $T->set_var('story_topic_image_url', $imageurl );
                $topicimage = '<img src="' . $imageurl . '" class="float'
                            . $_CONF['blog_image_align'] . '" alt="'
                            . $topicname . '" title="' . $topicname . '"' . XHTML . '>';
                $T->set_var('story_anchortag_and_image',
                    COM_createLink(
                        $topicimage,
                        $topicurl,
                        array('rel'=>"category tag")
                    )
                );
                $T->set_var('story_topic_image', $topicimage );
                $topicimage_noalign = '<img src="' . $imageurl . '" alt="'
                            . $topicname . '" title="' . $topicname . '"' . XHTML . '>';
                $T->set_var('story_anchortag_and_image_no_align',
                    COM_createLink(
                        $topicimage_noalign,
                        $topicurl,
                        array('rel'=>"category tag")
                    )
                );
                $T->set_var('story_topic_image_no_align', $topicimage_noalign);
            }
        }
        $T->set_var('story_topic_url', $topicurl);

        $recent_post_anchortag = '';
        $T->set_var('lang_permalink', $LANG01[127]);

        $show_comments = true;

        // n = 'Compact display' for list of stories. p = 'Preview' mode.
        if ($mode & BLOG_COMPACT) {

            // Not used just get so we know whether to add "read more"
            $bodytext  = $this->bodytext;

            $attributes = ' class="non-ul"';
            $attr_array = array('class' => 'non-ul');
            if (!empty($query)) {
                $attributes .= ' rel="bookmark"';
                $attr_array['rel'] = 'bookmark';
            }
            $T->set_var('start_storylink_anchortag',
                              '<a href="' . $blogUrl . '"' . $attributes . '>');
            $T->set_var('end_storylink_anchortag', '</a>');
            $T->set_var('story_title_link',
                COM_createLink(
                        $this->DisplayElements('title'),
                        $blogUrl,
                        $attr_array
                )
            );
        } else {
            $T->set_var('story_title_link', $this->DisplayElements('title'));
        }

        if ($mode & (BLOG_FULL | BLOG_PREVIEW | BLOG_PRINT)) {

            if (empty($bodytext)) {
                $T->set_var('story_introtext', $introtext);
                $T->set_var('story_text_no_br', $introtext);
            } else {
                if ($_CONF['allow_page_breaks'] == 1 && $mode == BLOG_FULL) {
                    // page breaks only apply to displayed articles
                    $page = (int)$page;
                    if ($page < 1) $page = 1;
                    elseif ($page > 1) {
                        $introtext = '';
                    }
                    $blog_array = explode('[page_break]', $bodytext);
                    $pagelinks = COM_printPageNavigation(
                        $blogUrl, $page, count($blog_array),
                        'view=', $_CONF['url_rewrite'], $LANG01[118]);
                    if (count($blog_array) > 1) {
                        $bodytext = $blog_array[$page - 1];
                    }
                    $T->set_var('page_selector', $pagelinks);

                    if ( ( $_CONF['page_break_comments'] == 'last' &&
                           $page < count($blog_array) )
                        ||
                         ( $_CONF['page_break_comments'] == 'first' &&
                           $page != 1 )
                    ) {
                        $show_comments = false;
                    }
                    //$T->set_var('story_page', $page);
                }

                $T->set_var('story_introtext', $introtext . 
                                '<br ' . XHTML . '>' . $bodytext);
                //$T->set_var('story_text_no_br', $introtext . $bodytext);
            }
            /*$T->set_var('story_introtext_only', $introtext);
            $T->set_var('story_bodytext_only', $bodytext );*/

            /*if ( ($_CONF['trackback_enabled'] || $_CONF['pingback_enabled']) &&
                    SEC_hasRights( 'story.ping') ) {
                $url = $_CONF['site_admin_url']
                     . '/trackback.php?mode=sendall&amp;id=' . $this->sid;
                $T->set_var('send_trackback_link',
                    COM_createLink($LANG_TRB['send_trackback'], $url)
                );
                $pingico = '<img src="' . $_CONF['layout_url'] . '/images/sendping.'
                    . $_IMAGE_TYPE . '" alt="' . $LANG_TRB['send_trackback']
                    . '" title="' . $LANG_TRB['send_trackback'] . '"' . XHTML . '>';
                $T->set_var('send_trackback_icon', 
                            COM_createLink($pingico, $url)
                );
                $T->set_var('send_trackback_url', $url);
                $T->set_var('lang_send_trackback_text',
                                   $LANG_TRB['send_trackback']);
            }*/
            /*$T->set_var('story_display',
                        $mode == BLOG_PREVIEW ? 'preview' : 'blog');
            $T->set_var('story_counter', 0);*/
        } else {
            $T->set_var('story_introtext', $introtext);

            if (!empty($bodytext)) {
                $numwords = COM_numberFormat(sizeof(explode(' ', 
                                    strip_tags($bodytext))));
                $T->set_var(array(
                    'lang_readmore'     => $LANG01[2],
                    'lang_readmore_words' => $LANG01[62],
                    'readmore_words'    => $numwords,
                    'readmore_link' => COM_createLink($LANG01[2], $blogUrl,
                                array('class'=>'blog-read-more-link')),
                                //" ($numwords {$LANG01[62]}) "),
                    // These are in case the admin wants to use a custom
                    // template
                    'start_readmore_anchortag' =>
                         '<a href="' . $blogUrl . 
                                    '" class="story-read-more-link">',
                    'end_readmore_anchortag'    => '</a>',
                    'read_more_class' => 'class="blog-read-more-link"',
                ) );
            }

            if ($this->commentcode >= 0  && $show_comments) {
                $commentsUrl = $blogUrl . '#comments';
                $T->set_var(array(
                    'comments_url'      => $commentsUrl,
                    'comments_text'     => COM_numberFormat(
                                $this->DisplayElements('comments')) . 
                                ' ' . $LANG01[3],
                    'comments_count'    => COM_numberFormat($this->comments),
                    'lang_comments'     => $LANG01[3],
                ) );
                $comments_with_count = sprintf($LANG01[121], 
                    COM_numberFormat($this->comments));

                if ($this->comments > 0 ) {
                    $result = DB_query("SELECT 
                            UNIX_TIMESTAMP(date) AS day, username, fullname,
                            c.uid as cuid 
                        FROM {$_TABLES['comments']} c,
                            {$_TABLES['users']} u
                        WHERE u.uid = c.uid 
                        AND c.sid = '" . DB_escapeString($this->sid) . "' 
                        ORDER BY date desc 
                        LIMIT 1");
                    $C = DB_fetchArray($result);

                    $recent_post_anchortag = '<span class="storybyline">'
                            . $LANG01[27] . ': '
                            . strftime( $_CONF['daytime'], $C['day'] ) . ' '
                            . $LANG01[104] . ' '
                            . COM_getDisplayName($C['cuid'],
                                    $C['username'], $C['fullname'])
                            . '</span>';
                    $T->set_var('comments_with_count', COM_createLink($comments_with_count, $commentsUrl));
                    $T->set_var('start_comments_anchortag', '<a href="'
                            . $commentsUrl . '">');
                    $T->set_var('end_comments_anchortag', '</a>');
                } else {
                    $T->set_var('comments_with_count', $comments_with_count);
                    $recent_post_anchortag = COM_createLink($LANG01[60],
                        $_CONF['site_url'] . '/comment.php?sid=' . $this->sid
                            . '&amp;pid=0&amp;type=blog');
                }

                if ($this->commentcode == 0 &&
                        ($_CONF['commentsloginrequired'] == 0 || 
                        !COM_isAnonUser())) {
                    $postCommentUrl = $_CONF['site_url'] . '/comment.php?sid='
                                . $this->sid . '&amp;pid=0&amp;type=' 
                                . $_BLOG_CONF['pi_name'] . '&amp;title='
                                . urlencode($this->title);
                    $T->set_var('post_comment_link',
                            COM_createLink($LANG01[60], $postCommentUrl,
                                           array('rel' => 'nofollow')));
                    $T->set_var('lang_post_comment', $LANG01[60]);
                    $T->set_var('start_post_comment_anchortag',
                                       '<a href="' . $postCommentUrl
                                       . '" rel="nofollow">');
                    $T->set_var('end_post_comment_anchortag', '</a>');
                }
            }

            /*if ( ($_CONF['trackback_enabled'] || $_CONF['pingback_enabled']) &&
                    $this->DisplayElements('trackbackcode') >= 0 &&
                    $show_comments ) {
                $num_trackbacks = COM_numberFormat($this->trackbacks);
                $trackbacksUrl = $blogUrl . '#trackback';
                $T->set_var('trackbacks_url', $trackbacksUrl );
                $T->set_var('trackbacks_text', $num_trackbacks . ' '
                                        . $LANG_TRB['trackbacks'] );
                $T->set_var('trackbacks_count', $num_trackbacks );
                $T->set_var('lang_trackbacks', $LANG_TRB['trackbacks'] );
                $T->set_var('trackbacks_with_count', COM_createLink(
                        sprintf($LANG01[122], $num_trackbacks),
                        $trackbacksUrl)
                );

                if (SEC_hasRights('story.ping')) {
                    $pingurl = $_CONF['site_admin_url']
                        . '/trackback.php?mode=sendall&amp;id=' . $this->sid;
                    $pingico = '<img src="' . $_CONF['layout_url'] . '/images/sendping.'
                        . $_IMAGE_TYPE . '" alt="' . $LANG_TRB['send_trackback']
                        . '" title="' . $LANG_TRB['send_trackback'] . '"' . XHTML . '>';
                    $T->set_var('send_trackback_icon',
                        COM_createLink($pingico, $pingurl)
                    );
                }

                if ($this->trackbacks > 0) {
                    $T->set_var('trackbacks_with_count',
                        COM_createLink(
                            sprintf($LANG01[122], $num_trackbacks),
                            $trackbacksUrl)
                    );
                } else {
                    $T->set_var('trackbacks_with_count',
                            sprintf($LANG01[122], $num_trackbacks)
                    );
                }
            }*/

            if ($_CONF['hideemailicon'] == 1 ||
                (COM_isAnonUser() && $_CONF['loginrequired'] == 1) ||
                $_CONF['emailstoryloginrequired'] == 1) {
                $T->set_var('email_icon', '');
            } else {
                $emailUrl = BLOG_URL . '/index.php?mode=emailblog&amp;sid=' .
                        $this->sid;
                $emailicon = '<img src="' . $_CONF['layout_url'] 
                    . '/images/mail.'
                    . $_IMAGE_TYPE . '" alt="' . $LANG01[64] . '" title="'
                    . $LANG11[2] . '"' . XHTML . '>';
                $T->set_var('email_icon',
                    COM_createLink($emailicon, $emailUrl)
                );
            }

            if ($_CONF['hideprintericon'] == 0) {
                $printUrl = BLOG_URL . '/index.php?mode=print&amp;sid=' .
                            $this->sid;
                $printicon = '<img src="' . $_CONF['layout_url']
                    . '/images/print.' . $_IMAGE_TYPE . '" alt="' . $LANG01[65]
                    . '" title="' . $LANG11[3] . '" ' . XHTML . '>';
                $T->set_var('print_icon',COM_createLink($printicon, $printUrl, 
                                array('rel' => 'nofollow',
                                    'target' => '_new'))
                );
            }
            $T->set_var('pdf_icon', '');

            if ($_CONF['backend'] == 1) {
                $result = DB_query("SELECT filename, title 
                        FROM {$_TABLES['syndication']} 
                        WHERE type = 'blog' 
                        AND topic = '" . DB_escapeString($this->tid) . "' 
                        AND is_enabled = 1");
                $feeds = DB_numRows($result);
                while ($A = DB_fetchArray($result, false)) {
                    list($filename, $title) = DB_fetchArray($result);
                    $feedUrl = SYND_getFeedUrl($A['filename']);
                    $feedTitle = sprintf($LANG11[6], $A['title']);
                }
                if ($feeds > 0) {
                    $feedicon = '<img src="'. $_CONF['layout_url'] . '/images/rss_small.'
                             . $_IMAGE_TYPE . '" alt="'. $feedTitle
                             .'" title="'. $feedTitle .'"' . XHTML . '>';
                    $T->set_var('feed_icon',
                                COM_createLink($feedicon, $feedUrl,
                                array("type" =>"application/rss+xml")));
                }
            }
            //$T->set_var('story_display', 'index');

            $storycounter++;
            //$T->set_var('story_counter', $storycounter);

        }

        $T->set_var('recent_post_anchortag', $recent_post_anchortag );

        if ($this->isEditor && $mode != BLOG_PREVIEW) {
            $editicon = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
            $editiconhtml = '<img src="' . $editicon . '" alt="' . $LANG01[4] . 
                '" title="' . $LANG01[4] . '"' . XHTML . '>';
            $edit_url = BLOG_ADMIN_URL . '/index.php?edit=x&amp;sid=' . $this->sid;
            $T->set_var('edit_icon', COM_createLink($editiconhtml, $edit_url));
        }

        PLG_templateSetVars($blog_filevar, $T);

        if ($_CONF['rating_enabled'] != 0 && $mode != BLOG_PREVIEW) {
            if (@in_array($this->sid, $ratedIds)) {
                $static = true;
                $voted = 1;
            } else {
                $static = 0;
                $voted = 0;
            }
            $uid = isset($_USER['uid']) ? $_USER['uid'] : 1;
            if ($_CONF['rating_enabled'] == 2 && $uid != $this->author_id) {
                $T->set_var('rating_bar', RATING_ratingBar('blog',
                    $this->sid, $this->votes, $this->rating, 
                    $voted, 5, $static, 'sm'), false, true);
            } elseif (!COM_isAnonUser() && $uid != $this->author_id) {
                $T->set_var('rating_bar', RATING_ratingBar('blog',
                    $this->sid, $this->votes, $this->rating,
                    $voted, 5, $static, 'sm'), false, true);
            } else {
                // Make the rating bar static if we're the item's author
                $T->set_var('rating_bar', RATING_ratingBar('blog',
                    $this->sid, $this->votes, $this->rating,
                    1, 5, TRUE, 'sm'), false, true);
            }
        } else {
            // Rating bar is disabled
            $T->set_var('rating_bar', '', false, true);
        }

        $T->parse('finalstory', 'blog');
        return $T->finish($T->get_var('finalstory'));

    }   // function Render()


    /**
    *   Get the date & time form options for a given time.
    *
    *   @param  string  $datetime   The date & time as YYYY-MM-DD hh:mm:ss
    *   @param  string  $ampm_varname   The variable name for the am/pm select
    *   @return array       Array of form fields
    */
    private function _getDateTimeOptions($datetime, $ampm_varname)
    {
        global $_CONF;

        $hour_mode = $_CONF['hour_mode'] == 12 ? 12 : 24;

        // Explode the given date & time into its components
        list($date, $time) = explode(' ', $datetime);
        list($year, $month, $day) = explode('-', $date);
        list($hour, $minute, $second) = explode(':', $time);
        $ampm = '';
        if ($hour_mode == 12) {
            if ($hour >= 12) {
                if ($hour > 12) {
                    $hour -= 12;
                }
                $ampm = 'pm';
            } else {
                $ampm = 'am';
            }
        }
        $ampm_select = COM_getAmPmFormSelection($ampm_varname, $ampm);
        if (empty($ampm_select)) {
            // have a hidden field to 24 hour mode to prevent JavaScript errors
            $ampm_select = '<input type="hidden" name="' . $ampm_varname . 
                    '" value=""' . XHTML . '>';
        }

        return array(
            'month_options'     => COM_getMonthFormOptions($month),
            'day_options'       => COM_getDayFormOptions($day),
            'year_options'      => COM_getYearFormOptions($year),
            'hour_options'      => COM_getHourFormOptions($hour, $hour_mode),
            'minute_options'    => COM_getMinuteFormOptions($minute),
            'ampm_options'      => $ampm_select,
        );
    }


    /**
    *   Creates a SQL-formatted date-time string from component parts
    *   Expects an array of form values containing fields names
    *   {$v}_day, {$v}_month, etc.  For example, if the form fields are
    *   named "publish_month", "publish_day", "publish_year", this function
    *   is called as _makeDate($A, 'publish').
    *
    *   @param  array   $A      Array of form fields
    *   @param  string  $v      Field base name
    *   @return string          Date & Time as "YYYY-MM-DD HH:mm:ss"
    */
    private function _makeDate($A, $v)
    {
        global $_CONF;

        if (empty($A[$v . '_year']) || 
            empty($A[$v . '_month']) || 
            empty($A[$v . '_day'])) {
            // Need to have all 3 fields to be valid input
            return date('Y-m-d H:i:s');
        }

        // Get the time value.  Time fields are optional
        if (!empty($A[$v . '_hour'])) {
            $hour = (int)$A[$v . '_hour'];
            if ($_CONF['hour_mode'] == 12 && isset($A[$v . '_ampm']) &&
                $A[$v . '_ampm'] == 'pm') {
                $hour += 12;
                if ($hour > 23) $hour = 12;
            }
        } else {
            $A[$v . '_hour'] = 0;
            $A[$v . '_minute'] = 0;
            $A[$v . '_second'] = 0;
        }

        $datetime = sprintf('%04d-%02d-%02d %02d:%02d:%02d',
            (int)$A[$v . '_year'], (int)$A[$v . '_month'], (int)$A[$v . '_day'],
            $hour, (int)$A[$v . '_minute'], (int)$A[$v . '_second']);
        return $datetime;
        
    }


    /**
    *   Provide the blog editor
    *
    *   @return string  HTML for the editor form
    */
    function Edit($action = 'edit')
    {
        global $_CONF, $_BLOG_CONF, $_TABLES, $_USER,
            $LANG_BLOG, $LANG24, $LANG_ADMIN, $LANG_ACCESS, $LANG12;

        $retval = '';
        $saveoption = $action;  // good for most actions

        switch ($action) {
        case 'edit':
        case 'clone' :
        case 'preview':
            $title = $LANG24[5];
            $lang_saveoption = $LANG_ADMIN['save'];
            $submission = false;
            $saveoption = 'save';
            break;
        case 'moderate':
            $title = $LANG24[90];
            $lang_saveoption = $LANG_ADMIN['moderate'];
            $submission = true;
            $saveoption = 'approvesubmission';
            break;
        case 'draft':
            $title = $LANG24[91];
            $lang_saveoption = $LANG_ADMIN['save'];
            $submission = true;
            $action = 'edit';
            break;
        default :
            $title = $LANG24[5];
            $lang_saveoption = $LANG_ADMIN['save'];
            $saveoption = 'save';
            $submission = false;
            $action = 'edit';
            break;
        }

        if (!isset($_CONF['hour_mode'])) {
            $_CONF['hour_mode'] = 12;
        }

        if (!empty($currenttopic)) {
            $allowed = DB_getItem($_TABLES['topics'], 'tid',
                        "tid = '" . DB_escapeString($currenttopic) . "'" .
                                COM_getTopicSql('AND'));

            if ($allowed != $currenttopic) {
                $currenttopic = '';
            }
        }

        if (empty($currenttopic) && $this->tid == '') {
            $this->tid = DB_getItem($_TABLES['topics'], 'tid',
                        'is_default = 1' . COM_getPermSQL ('AND'));
        } elseif ($this->tid == '') {
            $this->tid = $currenttopic;
        }

        if ($this->isEditor || $this->isModerator) {
            $allowedTopicList = COM_topicList('tid,topic', $this->tid, 1, true, 0);
            $author_select = COM_optionList($_TABLES['users'], 
                    'uid,username', $this->owner_id);
            $T = new Template(BLOG_PI_PATH . '/templates/admin');
            $T->set_var('isAdmin', 'true');
        } else {
            $allowedTopicList = COM_topicList('tid,topic', $this->tid, 1, true);
            $T = new Template(BLOG_PI_PATH . '/templates');
            $author_select = '';
        }
        if ($allowedTopicList == '') {
            $display .= COM_startBlock($LANG_ACCESS['accessdenied'], '',
                                COM_getBlockTemplate('_msg_block', 'header'));
            $display .= $LANG24[42];
            $display .= COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
            COM_accessLog("User {$_USER['username']} tried to illegally access story $sid. No allowed topics.");
            return $display;
        }

        if ($_BLOG_CONF['adveditor'] > 0) {
            $advanced_editormode = true;
            USES_class_navbar();
            $T->set_file(array('editor' => 'editor_advanced.thtml'));
            if (file_exists($_CONF['path_layout'] . '/fckstyles.xml')) {
                $T->set_var('glfusionStyleBasePath', $_CONF['layout_url']);
            } else {
                $T->set_var('glfusionStyleBasePath', 
                        $_CONF['site_url'] . '/fckeditor');
            }
            $T->set_var(array(
                'change_editormode'     => 'onchange="change_editmode(this);"',
                'show_preview'          => 'none',
                'lang_expandhelp'       => $LANG24[67],
                'lang_reducehelp'       => $LANG24[68],
                'lang_publishdate'      => $LANG24[69],
                'lang_toolbar'          => $LANG24[70],
                'toolbar1'              => $LANG24[71],
                'toolbar2'              => $LANG24[72],
                'toolbar3'              => $LANG24[73],
                'toolbar4'              => $LANG24[74],
                'toolbar5'              => $LANG24[75],
            ) );

            if ($this->advanced_editor_mode == 1 || 
                    $this->postmode == 'adveditor') {
                $T->set_var(array(
                    'show_texteditor'   => 'none',
                    'show_htmleditor'   => '',
                ) );
            } else {
                $T->set_var(array(
                    'show_texteditor'   => '',
                    'show_htmleditor'   => 'none',
                ) );
            }

        } else {
            // Not using the advanced editor
            $T->set_file(array('editor' => 'editor.thtml'));
            $advanced_editormode = false;
        }

        if ($this->hasContent()) {
            $previewContent = $this->Render(BLOG_PREVIEW);
            if ($advanced_editormode && $previewContent != '') {
                $T->set_var('preview_content', $previewContent);
            } elseif ($previewContent != '') {
                $display = COM_startBlock($LANG24[26], '',
                                COM_getBlockTemplate('_admin_block', 'header'));
                $display .= $previewContent;
                $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
            }
        }

        if ($advanced_editormode && $this->isEditor) {
            // Set up the navigation bar.  Only used if advanced_editor is on
            $navbar = new navbar;
            $i = 0;
            if (!empty($previewContent)) {
                $navbar->add_menuitem($LANG24[79],
                    'showhideEditorDiv("preview",'.$i++.');return false;',true);
            }
            $navbar->add_menuitem($LANG24[80],
                    'showhideEditorDiv("editor",'.$i++.');return false;',true);
            $navbar->add_menuitem($LANG24[82],
                    'showhideEditorDiv("images",'.$i++.');return false;',true);
            $navbar->add_menuitem($LANG24[81],
                    'showhideEditorDiv("publish",'.$i++.');return false;',true);
            $navbar->add_menuitem($LANG24[83],
                    'showhideEditorDiv("archive",'.$i++.');return false;',true);
            $navbar->add_menuitem($LANG24[84],
                    'showhideEditorDiv("perms",'.$i++.');return false;',true);
            $navbar->add_menuitem($LANG24[85],
                    'showhideEditorDiv("all",'.$i++.');return false;',true);

            if ($action == 'preview') {
                // Make the "preview" tab active
                $T->set_var(array(
                    'show_preview'      => '',
                    'show_htmleditor'   => 'none',
                    'show_texteditor'   => 'none',
                    'show_submitoptions' => 'none',
                ) );
                $navbar->set_selected($LANG24[79]);
            } else {
                $navbar->set_selected($LANG24[80]);
            }
            $T->set_var('navbar', $navbar->generate());
        }

        // start generating the story editor block
        //$display .= COM_startBlock ($title, '',
        //                COM_getBlockTemplate ('_admin_block', 'header'));
        $oldsid = $this->originalSid;
        if (!empty($oldsid)) {
            $delbutton = '<input type="submit" value="' . $LANG_ADMIN['delete']
                   . '" name="deletestory"%s' . XHTML . '>';
            $jsconfirm = ' onclick="return confirm(\'' . $MESSAGE[76] . '\');"';
            $T->set_var('delete_option', sprintf ($delbutton, $jsconfirm));
            $T->set_var('delete_option_no_confirmation', sprintf ($delbutton, ''));
        }
        if ($submission || ($this->type == 'submission')) {
            $T->set_var ('submission_option',
                    '<input type="hidden" name="type" value="submission"' . XHTML . '>');
        }

        if ($this->owner_id > 1) {
            $owner_name = COM_getDisplayName($this->owner_id);
            $author_name = $owner_name;
            $lang_loginout = $LANG12[34];
            $status_url = $_CONF['site_url'].'/users.php?mode=logout';
            $allow_signup = '';
        } else {
            $status_url = $_CONF['site_url'] . '/users.php';
            $lang_loginout = $LANG12[2];
            $allow_signup = $_CONF['disable_new_user_registration'] ? '' : 'true';
            $author_name = '';
            $owner_name = '';
        }

        $T->set_var(array(
            'status_url'    => $status_url,
            'author_name'   => $author_name,
            //'ownername'     => $ownername,
            'allow_signup'  => $allow_signup,
            'lang_loginout' => $lang_loginout,
        ) );

        $pub_opt = $this->_getDateTimeOptions($this->date, 'publish_ampm');
        $exp_opt = $this->_getDateTimeOptions($this->expire, 'expire_ampm');
        $cmt_opt = $this->_getDateTimeOptions($this->comment_expire, 
                                'cmt_close_ampm');

        // Provide the "featured" selector if the user is allowed to feature
        // an blog.  Otherwise set the featured status off
        if ($this->isAdmin || $_CONF['onlyrootfeatures'] == 0) {
            $featured_options = "<select name=\"featured\">" . LB
                        . COM_optionList($_TABLES['featurecodes'], 'code,name',
                                $this->featured)
                        . "</select>" . LB;
        } else {
            $featured_options = "<input type=\"hidden\" name=\"featured\" value=\"0\"" . XHTML . ">";
        }

        $post_options = COM_optionList($_TABLES['postmodes'], 'code,name',
                        $this->postmode);
        if ($_BLOG_CONF['adveditor'] > 0 && $this->isEditor) {
            $sel = $this->advanced_editor_mode == 1 || 
                    $this->postmode == 'adveditor' ? BLOG_SELECTED : '';
            $post_options .= '<option value="adveditor" ' . $sel . '>' .
                    $LANG24[86].'</option>';
        }

        /*if ($_CONF['wikitext_editor']) {
            $sel = $this->postmode == 'wikitext' ? BLOG_SELECTED : '';
            $post_options .= '<option value="wikitext" ' . $sel . '>' .
                    $LANG24[88].'</option>';
        }*/

        $action_url = $this->isEditor ? 
                BLOG_ADMIN_URL . '/index.php' : 
                BLOG_URL . '/index.php';

        $T->set_var(array(
            'use_title2id'      => $_BLOG_CONF['use_title2id'] == 1 ? 'true' : '',
            'action_url'        => $action_url,
            'pi_url'            => BLOG_URL,
            'hour_mode'         => $_CONF['hour_mode'],
            'author_name'       => $author_name,
            'author_select'     => $author_select,
            'story_uid'         => $this->author_id,
            'owner_username'    => DB_getItem($_TABLES['users'], 'username', 
                                    'uid = ' . $this->owner_id),
            'owner_name'        => $ownername,
            'owner'             => $ownername,
            'owner_id'          => $this->owner_id,
            'permissions_editor' => SEC_getPermissionsHTML(
                    $this->perm_owner, $this->perm_group,
                    $this->perm_members, $this->perm_anon),
            'permissions_msg'   => $LANG_ACCESS['permmsg'],
            'publish_second'    => date('s', $this->date),
            'publishampm_selection' => $pub_opt['ampm_options'],
            'publish_month_options' => $pub_opt['month_options'],
            'publish_day_options'   => $pub_opt['day_options'],
            'publish_year_options'  => $pub_opt['year_options'],
            'publish_hour_options'  => $pub_opt['hour_options'],
            'publish_minute_options' => $pub_opt['minute_options'],
            'story_unixstamp'   => strtotime($this->_date),
            'expireampm_selection'  => $exp_opt['ampm_options'],
            'expire_month_options'  => $exp_opt['month_options'],
            'expire_day_options'    => $exp_opt['day_options'],
            'expire_year_options'   => $exp_opt['year_options'],
            'expire_hour_options'   => $exp_opt['hour_options'],
            'expire_minute_options' => $exp_opt['minute_options'],
            'story_title'       => $this->title,
            'topic_options'     => $allowedTopicList,
            'show_topic_icon_checked' => $this->show_topic_icon == 1 ?
                                 BLOG_CHECKED : '',
            'is_checked'        =>$this->draft_flag ? BLOG_CHECKED : '',
            'status_options'    => COM_optionList($_TABLES['statuscodes'], 
                                'code,name', $this->statuscode),
            'comment_options'   => COM_optionList($_TABLES['commentcodes'], 
                                'code,name', $this->commentcode),
            'trackback_options' => COM_optionList($_TABLES['trackbackcodes'],
                                'code,name', $this->trackbackcode),
            'cmt_close_month_options'   => $cmt_opt['month_options'],
            'cmt_close_day_options'     => $cmt_opt['day_options'],
            'cmt_close_year_options'    => $cmt_opt['year_options'],
            'cmt_close_ampm_selection'  => $cmt_opt['ampm_options'],
            'cmt_close_hour_options'    => $cmt_opt['hour_options'],
            'cmt_close_minute_options'  => $cmt_opt['minute_options'],
            'cmt_close_second_options'  => $cmt_opt['second_options'],
            'featured_options'  => $featured_options,
            'frontpage_options' => COM_optionList($_TABLES['frontpagecodes'], 
                                'code,name', $this->frontpage),
            'story_introtext'   => $this->_editText($this->introtext),
            'story_bodytext'    => $this->_editText($this->bodytext),
            'no_javascript_return_link' => sprintf($LANG24[78],
                                    $_CONF['site_admin_url'], $sid),
            'post_options'      => $post_options,
            'allowed_html'      => COM_allowedHTML(),
            'story_hits'        => $this->hits,
            'story_comments'    => $this->comments,
            'story_trackbacks'  => $this->trackbacks,
            'numemails'         => $this->numemails,
            'story_id'          => $this->sid,
            'old_story_id'      => $this->sid,
            'lang_save'         => $lang_saveoption,
            'saveoption'        => $saveoption,
            'gltoken_name'      => CSRF_TOKEN,
            'gltoken'           => SEC_createToken(),

            // Language strings - TODO: move to template
            /*'lang_preview'      => $LANG_ADMIN['preview'],
            'lang_cancel'       => $LANG_ADMIN['cancel'],
            'lang_delete'       => $LANG_ADMIN['delete'],
            'lang_sid'          => $LANG24[12],
            'lang_emails'       => $LANG24[39],
            'lang_trackbacks'   => $LANG24[29],
            'lang_comments'     => $LANG24[19],
            'lang_author'       => $LANG24[7],
            'lang_accessrights' => $LANG_ACCESS['accessrights'],
            'lang_owner'        => $LANG_ACCESS['owner'],
            'lang_group'        => $LANG_ACCESS['group'],
            'lang_permissions'  => $LANG_ACCESS['permissions'],
            'lang_perm_key'     => $LANG_ACCESS['permissionskey'],
            'lang_date'         => $LANG24[15],
            'publish_date_explanation' => $LANG24[46],
            'expire_date_explanation' => $LANG24[46],
            'lang_archivetitle' => $LANG24[58],
            'lang_option'       => $LANG24[59],
            'lang_enabled'      => $LANG_ADMIN['enabled'],
            'lang_story_stats'  => $LANG24[87],
            'lang_optionarchive' => $LANG24[61],
            'lang_optiondelete' => $LANG24[62],
            'lang_title'        => $LANG_ADMIN['title'],
            'lang_topic'        => $LANG_ADMIN['topic'],
            'lang_show_topic_icon' => $LANG24[56],
            'lang_draft'        => $LANG24[34],
            'lang_mode'         => $LANG24[3],
            'lang_cmt_disable'  => $LANG24[63],
            'lang_introtext'    => $LANG24[16],
            'lang_bodytext'     => $LANG24[17],
            'lang_postmode'     => $LANG24[4],
            'lang_publishoptions' => $LANG24[76],
            'lang_nojavascript' => $LANG24[77],
            'lang_hits'         => $LANG24[18],*/
        ) );

        if ($this->advanced_editor_mode == 1 || 
                $this->postmode == 'adveditor' ||
                $this->postmode == 'plaintext') {
            $T->set_var('show_allowedhtml', 'none');
        } else {
            $T->set_var('show_allowedhtml', '');
        }

        if ($_CONF['rating_enabled']) {
            $T->set_var(array(
                'rating'    => @number_format($this->rating, 2),
                'votes'     => $this->votes,
            ) );
        }

        $fileinputs = '';
        $saved_images = '';
        if ($_BLOG_CONF['maximages'] > 0) {
            $T->set_var('lang_images', $LANG24[47]);
            $icount = DB_count($_TABLES['article_images'],'ai_sid', $this->sid);
            if ($icount > 0) {
                $result_blogs = DB_query("SELECT * 
                    FROM {$_TABLES['article_images']} 
                    WHERE ai_sid = '{$this->sid}'");
                for ($z = 1; $z <= $icount; $z++) {
                    $I = DB_fetchArray($result_blogs, false);
                    // TODO: should be a plugin-specific directory for images
                    // or remove this ability
                    $saved_images .= $z . ') '
                        . COM_createLink($I['ai_filename'],
                            $_CONF['site_url'] . '/images/articles/' . 
                                $I['ai_filename'])
                    . '&nbsp;&nbsp;&nbsp;' . $LANG_ADMIN['delete']
                    . ': <input type="checkbox" name="delete[' .$I['ai_img_num']
                    . ']"' . XHTML . '><br' . XHTML . '>';
                }
            }

            $newallowed = $_BLOG_CONF['maximages'] - $icount;
            for ($z = $icount + 1; $z <= $_BLOG_CONF['maximages']; $z++) {
                $fileinputs .= $z . ') <input type="file" dir="ltr" name="file[]'
                        . '"' . XHTML . '>';
                if ($z < $_BLOG_CONF['maximages']) {
                    $fileinputs .= '<br' . XHTML . '>';
                }
            }
            $fileinputs .= '<br' . XHTML . '>' . $LANG24[51];
            if ($_CONF['allow_user_scaling'] == 1) {
                $fileinputs .= $LANG24[27];
            }
            $fileinputs .= $LANG24[28] . '<br' . XHTML . '>';
        }

        $T->set_var(array(
            'saved_images'      => $saved_images,
            'image_form_elements' => $fileinputs,
        ) );

        if ($this->cmt_close_flag) {
            $T->set_var('chk_cmt_close', BLOG_CHECKED);
            //$T->set_var('showcmtclosedisabled', 'false');
        } else {
            //$T->set_var('showcmtclosedisabled', 'true');
        }

        switch ($this->statuscode) {
        case BLOG_EXPIRE_ARCHIVE:
            $T->set_var(array(
                'chk_archiveflag'   => BLOG_CHECKED,
                'chk_exp_archive'   => BLOG_CHECKED,
                'showarchivedisabled' => 'false',
            ) );
            break;
        case BLOG_EXPIRE_DELETE:
            $T->set_var(array(
                'chk_archiveflag'   => BLOG_CHECKED,
                'chk_exp_delete'    => BLOG_CHECKED,
                'showarchivedisabled' => 'false',
            ) );
            break;
        default:
            $T->set_var('showarchivedisabled', 'true');
            break;
        }

        if (SEC_hasRights('blog.edit')) {
            $T->set_var('owner_dropdown', COM_buildOwnerList('owner_id', 
                        $this->owner_id));
        } else {
            $ownerInfo = '<input type="hidden" name="owner_id" value="'.
                    $this->owner_id . '" />' . $ownername;
            $T->set_var('owner_dropdown', $ownerInfo);
        }

        if (SEC_inGroup($this->group_id)) {
            $T->set_var('group_dropdown',
                            SEC_getGroupDropdown($this->group_id, 3));
        } else {
            $gdrpdown = '<input type="hidden" name="group_id" value="' .
                    $this->group_id . '"/>';
            $grpddown .= DB_getItem($_TABLES['groups'],'grp_name',
                    'grp_id=' . $this->group_id);
            $T->set_var('group_dropdown', $grpddown);
        }

        $T->parse('output', 'editor');
        $retval .= $T->finish($T->get_var('output'));
        //$retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $retval;

    }   // function Edit()


    /**
    *   Return a topic ID.
    *   May return the ID provided, or will look for a default topic or
    *   any topic that that the user is allowed to access
    *
    *   @param  string  $tid    Topic ID (optional)
    *   @return string          Provided ID, or one from the database
    */
    function getTopic($tid = '')
    {
        global $_TABLES;

        // If a topic id is provided, just return it
        if (!empty($tid)) return $tid;

        // Second choice, get the default topic
        $tid =  DB_getItem($_TABLES['topics'], 'tid',
                        'is_default = 1' . COM_getPermSQL('AND'));

        // Final choice, get any topic that the user has access to
        if (empty($tid)) {
            $tid = DB_getItem($_TABLES['topics'], 'tid', COM_getPermSQL('AND'));
        }

        return $tid;
    }


    /**
    *   Extract links from an HTML-formatted text.
    *
    *   Collects all the links in a story and returns them in an array.
    *
    *   @param    int     $maxlength  max. length of text in a link (can be 0)
    *   @return   array   an array of strings of form <a href="...">link</a>
    */
    function extractLinks($maxlength = 26)
    {
        $rel = array();
        $fulltext = $this->introtext . ' ' . $this->bodytext;

        // Only match anchor tags that contain 'href="<something>"'
        preg_match_all( "/<a[^>]*href=[\"']([^\"']*)[\"'][^>]*>(.*?)<\/a>/i", 
            $fulltext, $matches );

        for ($i = 0; $i < count($matches[0]); $i++) {
            $matches[2][$i] = strip_tags($matches[2][$i]);
            if (!MBYTE_strlen(trim($matches[2][$i]))) {
                $matches[2][$i] = strip_tags($matches[1][$i]);
            }

            // if link is too long, shorten it and add ... at the end
            if ($maxlength > 0 && MBYTE_strlen($matches[2][$i]) > $maxlength) {
                $matches[2][$i] = substr($matches[2][$i], 0, $maxlength - 3) . 
                    '...';
            }

            $rel[] = '<a href="' . $matches[1][$i] . '">'
                   . str_replace(array("\015", "\012"), '', $matches[2][$i])
                   . '</a>';
        }

        return $rel;
    }


    /**
    *   Save a blog.
    *
    *   @param  array   $A      Array of data, e.g. $_POST
    *   @return boolean         True on success, False on failure
    */
    function Save($A = array())
    {
        global $_TABLES, $_CONF, $_BLOG_CONF;

        if (!empty($A)) {
            // Set all variables from $A, including updating the SID
            $this->setVars($A);
            if (isset($A['old_sid']) && !empty($A['old_sid']))
                $this->originalSid = $A['old_sid'];
            else
                $this->originalSid = $this->sid;
        }

        $newSid = DB_escapeString($this->sid);
        if (!$this->isNew) {        // Updating a record
            $oldSid = DB_escapeString($this->originalSid);
            $sql1 = "UPDATE {$_TABLES['blogs']} SET ";
            $sql3 = " WHERE sid='{$oldSid}'";
            // Acquire Rating / Votes
            list($rating_id, $rating, $votes) = 
                RATING_getRating($_BLOG_CONF['pi_name'], $this->sid);
            $this->rating = $rating;
            $this->votes = $votes;

        } else {        // Inserting a new record
            $this->originalSid = $this->sid;
            if (empty($newSid)) $newSid = COM_makeSid();
            $sql1 = "INSERT INTO {$_TABLES['blogs']} SET ";
            $sql3 = '';

            // Clear variables that aren't set for new items
            // TODO: redundant, since we call setDefaults() now?
            $this->hits = 0;
            $this->votes = 0;
            $this->numemails = 0;
            $this->comments = 0;
            $this->trackbacks = 0;
            $this->rating = 0;

        }

        // Check for uploaded images to process
        if (count($_FILES) && $_BLOG_CONF['maximages'] > 0) {
            USES_blog_class_blogimage();
            $Image = new BlogImage($this->sid);
            $Image->uploadFiles();
            // TODO - error checking needed?
        }

        // If this is going right into the Archive topic, then clear
        // the frontpage & featured flags.
        if ($_BLOG_CONF['archivetid'] == $this->tid) {
            $this->featured = 0;
            $this->frontpage = 0;
            $this->statuscode = BLOG_EXPIRE_ARCHIVE;
        }

        // If a featured, non-draft, that goes live straight away, unfeature
        // other stories in same topic:
        if ($this->featured == '1') {
            // there can only be one non-draft featured story
            if ($this->draft_flag == 0 && $this->date <= time()) {

                if ($this->frontpage == 1) {
                    // un-feature any featured frontpage story
                    DB_query("UPDATE {$_TABLES['blogs']} 
                            SET featured = 0 
                            WHERE featured = 1 
                            AND draft_flag = 0 
                            AND frontpage = 1 
                            AND date <= NOW()");
                }

                // un-feature any featured story in the same topic
                DB_query("UPDATE {$_TABLES['blogs']} 
                        SET featured = 0 
                        WHERE featured = 1 
                        AND draft_flag = 0 
                        AND tid = '{$this->tid}' 
                        AND date <= NOW()");
            }
        }

        // Replace [imageX] tags in the story with actual image urls
        $this->insertImages();

        // If the new SID is different than the original, and if the
        // blog already exists in the database, then we have to update all 
        // the related tables with the new SID.
        if ($this->sid != $this->originalSid && !$this->isNew) {

            // If we've changed the SID, make sure the new one doesn't already
            // exist.  If it does, change it.  More elegant handling to follow
            $x = DB_count($_TABLES['blogs'], 'sid', $newSid);
            if ($x > 0) {
                $this->sid = COM_makeSid();
                $newSid = DB_escapeString($this->sid);
            }

            // Move Comments
            $sql = "UPDATE {$_TABLES['comments']} 
                    SET sid='$newSid'
                    WHERE type='{$_BLOG_CONF['pi_name']}'
                    AND sid='$checksid'";
            DB_query($sql);

            // Move Images
            $sql = "UPDATE {$_TABLES['article_images']} 
                    SET ai_sid = '{$newSsid}' 
                    WHERE ai_sid = '{$oldSid}'";
            DB_query($sql);

            // Move trackbacks
            $sql = "UPDATE {$_TABLES['trackback']} 
                    SET sid='{$newSid}' 
                    WHERE sid='{$oldSid}' 
                    AND type='{$_BLOG_CONF['pi_name']}'";
            DB_query($sql);

            // Move ratings
            $sql = "UPDATE {$_TABLES['rating']} 
                        SET item_id='{$newSid}' 
                        WHERE item_id='{$oldSid}'
                        AND type='{$_BLOG_CONF['pi_name']}'";
            DB_query($sql);
            $sql = "UPDATE {$_TABLES['rating_votes']} 
                        SET item_id='{$newSid}' 
                        WHERE item_id='{$oldSidid}' 
                        AND type='{$_BLOG_CONF['pi_name']}'";
            DB_query($sql);

            CACHE_remove_instance('blog_'.$this->originalSid);
        }

        // Acquire Comment Count
        $this->comments = DB_count($_TABLES['comments'],
                array('type', 'sid'),
                array($_BLOG_CONF['pi_name'], $this->sid));

        // Get the related URLs
        $this->related = implode("\n", $this->extractLinks());
        $sql2 = "sid = '$newSid',
            author_id = {$this->author_id},
            tid = '" . DB_escapeString($this->tid) . "',
            title = '" . DB_escapeString($this->title) . "',
            introtext = '" . DB_escapeString($this->introtext) . "',
            bodytext = '" . DB_escapeString($this->bodytext) . "',
            date = '" . DB_escapeString($this->date) . "',
            postmode = '" . DB_escapeString($this->postmode) . "',
            hits = {$this->hits},
            votes = {$this->votes},
            numemails = {$this->numemails},
            commentcode = {$this->commentcode},
            statuscode = {$this->statuscode},
            comments = {$this->comments},
            trackbacks = {$this->trackbacks},
            advanced_editor_mode = {$this->advanced_editor_mode},
            frontpage = {$this->frontpage},
            owner_id = {$this->owner_id},
            group_id = {$this->group_id},
            perm_owner = {$this->perm_owner},
            perm_group = {$this->perm_group},
            perm_members = {$this->perm_members},
            perm_anon = {$this->perm_anon},
            draft_flag = {$this->draft_flag},
            featured = {$this->featured},
            show_topic_icon = {$this->show_topic_icon},
            rating = {$this->rating},
            comment_expire = '" . DB_escapeString($this->comment_expire) . "',
            related = '" . DB_escapeString($this->related) . "',
            expire = '" . DB_escapeString($this->expire) . "'";

        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            return false;
        } else {
            return true;
        }

    }   // function Save()


    /**
    *   Delete the current blog record.
    *   Checks the current user's access to the object if not done by
    *   a system action, such as a scheduled task
    *
    *   @param  string  $sid    Optional ID, current object if empty
    *   @param  boolean $system True if done by a system action
    */
    function Delete($sid = '', $system = false)
    {
        global $_TABLES, $_BLOG_CONF;

        if ($sid == '' && is_object($this)) {
            $sid = $this->sid;
        }
        if (empty($sid) || (!$system && !$this->checkAccess(3))) {
            return false;
        }

        // Remove all rating records.  Also updates the current item,
        // so this gets done first.
        USES_lib_rating();
        RATING_resetRating($_BLOG_CONF['pi_name'], $sid);

        DB_delete($_TABLES['comments'], 
                array('sid', 'type'),
                array($sid, $_BLOG_CONF['pi_name']));
        DB_delete($_TABLES['trackbacks'], 
                array('sid', 'type'),
                array($sid, $_BLOG_CONF['pi_name']));
        DB_delete($_TABLES['blogs'], 
                array('sid', 'type'),
                array($sid, $_BLOG_CONF['pi_name']));

        Blog::deleteImages($sid);

        PLG_itemDeleted($sid, 'blog');

        // update RSS feed and Older Stories block
        COM_rdfUpToDateCheck();
        COM_olderStuff();

    }   // function Delete()


    /**
    *   Send the article via email
    *
    *   @param  string  $to         Recipient name
    *   @param  string  $toemail    Recipient address
    *   @param  string  $from       Sender name
    *   @param  string  $fromemail  Sender address
    *   @param  string  $msg        Message accompanying the article
    *   @return string              Redirect URL, with message ID
    */
    function Send($to, $toemail, $from, $fromemail, $msg)
    {
        global $_CONF, $_BLOG_CONF, $LANG_BLOG, $_TABLES, $_USER;
        global $LANG01, $LANG08;        // TODO: remove these

        $blogUrl = COM_buildUrl(BLOG_URL . '/index.php?sid=' . $this->sid);
        if ($_CONF['url_rewrite']) {
            $retval = COM_refresh($blogUrl . '?msg=85');
        } else {
            $retval = COM_refresh($blogUrl . '&amp;msg=85');
        }

        // check for correct $_CONF permission
        if (COM_isAnonUser() && (($_CONF['loginrequired'] == 1) ||
                             ($_CONF['emailstoryloginrequired'] == 1))) {
            return $retval;
        }

        // check if emailing of stories is disabled
        if ($_CONF['hideemailicon'] == 1) {
            return $retval;
        }

        // check mail speedlimit
        COM_clearSpeedlimit ($_CONF['speedlimit'], 'mail');
        if (COM_checkSpeedlimit ('mail') > 0) {
            return $retval;
        }

        $mailtext = sprintf($LANG_BLOG['mail_txt'], $from, $fromemail) . LB;
        if (strlen($msg) > 0) {
            $shortmsg = COM_filterHTML($msg);
            $mailtext .= LB . sprintf($LANG_BLOG['user_wrote'], $from) . 
                    $shortmsg . LB;
        }

        // just to make sure this isn't an attempt at spamming users ...
        $result = PLG_checkforSpam($mailtext, $_CONF['spamx']);
        if ($result > 0) {
            COM_updateSpeedlimit ('mail');
            COM_displayMessageAndAbort ($result, 'spamx', 403, 'Forbidden');
        }

        $mailtext .= 
                '------------------------------------------------------------'
                . LB . LB
                . COM_undoSpecialChars($Blog->title) . LB
                . strftime($_CONF['date'], $this->ux_date) . LB;

        if ($_CONF['contributedbyline'] == 1) {
            $author = COM_getDisplayName($this->author_id);
            $mailtext .= $LANG01[1] . ' ' . $author . LB;
        }

        $mailtext .= LB
        . COM_undoSpecialChars(strip_tags($this->DisplayElements('introtext')))
        . LB . LB
        . COM_undoSpecialChars(strip_tags($this->DisplayElements('bodytext')))
        . LB . LB
        . '------------------------------------------------------------' . LB;

        if ($Blog->commentcode == 0) { // comments allowed
            $mailtext .= $LANG08[24] . LB
            . COM_buildUrl(BLOG_URL . '/index.php?sid=' . $this->sid . 
                    '#comments');
        } else { // comments not allowed - just add the story's URL
            $mailtext .= $LANG08[33] . LB
            . COM_buildUrl(BLOG_URL . '/index.php?sid=' . $sid);
        }

        $mailto = COM_formatEmailAddress($to, $toemail);
        $mailfrom = COM_formatEmailAddress($from, $fromemail);
        $subject = COM_undoSpecialChars(strip_tags($this->title));

        $rc = COM_mail($mailto, $subject, $mailtext, $mailfrom, 0);
        COM_updateSpeedlimit('mail');

        if ($rc) {
            if ($_CONF['url_rewrite']) {
                $retval = COM_refresh($blogUrl . '?msg=27');
            } else {
                $retval = COM_refresh($blogUrl . '&amp;msg=27');
            }
        } else {
            // Increment numemails counter for story
            DB_query("UPDATE {$_TABLES['blogs']} 
                    SET numemails = numemails + 1 
                    WHERE sid = '".DB_escapeString($this->sid)."'");
            if ($_CONF['url_rewrite']) {
                $retval = COM_refresh($blogUrl . '?msg=26');
            } else {
                $retval = COM_refresh($blogUrl . '&amp;msg=26');
            }
        }
        return $retval;
    }


    /**
    *   Get item ids that match the type, up to the count
    *
    *   @return array   Array of sids
    */
    function getSids($type, $count)
    {
        global $_TABLES;

        $sids = array();
        $count = (int)$count;
        if ($count < 1) $count = 1;
        $featured = $type & BLOG_FEATURED ? 1 : 0;
        $sql = "SELECT sid FROM {$_TABLES['blogs']}
                WHERE featured = $featured 
                    AND draft_flag = 0
                    AND date <= NOW()
                ORDER BY date ASC
                LIMIT $count";
        //echo $sql;die;
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $sids[] = $A['sid'];
        }
        return $sids;
    }


    /**
    *   Toggle a boolean value in the database
    *
    *   @param  string  $field  Field name to change
    *   @param  integer $oldval Old value (1 or 0)
    *   @param  string  $sid    ID of article record to change
    *   @return integer         New value saved in DB
    */
    public static function toggle($field, $oldval, $sid)
    {
        global $_TABLES;

        // Set up variables and sanitize parameters.
        $newval = $oldval == 0 ? 1 : 0;
        $retval = $oldval;      // Assume no change
        $sql = array();

        switch ($field) {
        case 'featured':
            // Featuring an article requires all other articles in the same
            // topic to be un-featured, and also forces the article to the
            // frontpage.
            // TODO: Ajax doesn't update the checkboxes on the admin screen
            //  for the articles that are un-featured here.
            $tid = DB_getItem($_TABLES['blogs'], 'tid', "sid='$sid'");
            if (!empty($tid)) {
                $sql[] = "UPDATE {$_TABLES['blogs']}
                    SET featured = 0
                    WHERE tid='$tid'";
            }
            // If featuring, automatically move to the frontpage
            $fp_sql = $newval == 1 ? ', frontpage = 1 ' : '';
            $sql[] = "UPDATE {$_TABLES['blogs']}
                    SET featured = $newval $fp_sql
                    WHERE sid = '$sid'";
            break;

        case 'draft_flag':
        case 'frontpage':
            $sql[] = "UPDATE {$_TABLES['blogs']}
                    SET $field = $newval
                    WHERE sid = '$sid'";
            break;
        }

        // Perform the query.  Any error reverts $newval back to $oldval
        // and stops the loop
        if (!empty($sql)) {
            $retval = $newval;
            foreach ($sql as $S) {
                //echo $sql;die;
                DB_query($S, 1);
                if (DB_error()) {
                    $retval = $oldval;
                    break;
                }
            }
        }

        return $retval;
    }


    /**
    *   Find the date that is $days from a start date
    *
    *   @param  integer $days   Interval length in days
    *   @param  string  $start  Starting date, SQL date format
    *   @return string          Date in SQL format that is $days from $start
    */
    function AddDays($days, $start = '')
    {
        if ($start == '') $start = date('Y-m-d H:i:s');
        $days = (int)$days;
        return date('Y-m-d H:i:s', strtotime("+{$days} day"));
    }


}   // class BlogItem

?>
