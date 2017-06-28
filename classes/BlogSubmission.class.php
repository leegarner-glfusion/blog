<?php
//  $Id: BlogSubmission.class.php 17 2011-04-01 16:39:33Z root $
/**
*   @author     Lee P. Garner <lee AT leegarner DOT com>
*   @package    blog
*   @version    0.0.1
*   @copyright  Copyright &copy; 2011 Lee P. Garner
*   @license http://opensource.org/licenses/gpl-2.0.php
*           GNU General Public License v2
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

USES_blog_class_blog();


/**
*   Blog Submission handler
*   @package blog
*/
class BlogSubmission extends Blog
{

    /**
     * Constructor, creates a story, taking a (glFusion) database object.
     * @param $mode   string    Story class mode, either 'admin' or 'submission'
     */
    function __construct($sid = '')
    {
        global $_USER, $_BLOG_CONF, $_CONF;

        parent::__construct($sid);
        $this->isNew = true;
        if ($sid != '') {
            if (!$this->Read($sid)) {
                // Tried to load a non-existent blog.  Clear the sid
                // and set the error code for the caller to check
                $this->sid = '';
                $this->error = 1;
            } else {
                // Successful read
                $this->isNew = false;
            }
        } else {
            $this->sid = COM_makeSid();
        }

    }


    function setDefaults()
    {
        global $_CONF, $_BLOG_CONF, $_USER;

        $this->sid = '';
        $this->author_id = $_USER['uid'];
        $this->tid = '';
        $this->title = '';
        $this->introtext = '';
        $this->bodytext = '';
        $this->date = date('Y-m-d H:i:s');
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

        if (!$this->isModerator) {
            $this->error = 1;
            return false;
        }

        if ($sid != '')
            $this->sid = $sid;

        $sql = "SELECT *, UNIX_TIMESTAMP(date) AS ux_date
                FROM {$_TABLES['blog_submission']}
                WHERE sid = '{$this->sid}'";
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

        foreach (array('author_id', 'tid', 'title', 'introtext', 'bodytext',
                    'date')
                     as $name) {
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
            // Integer values
            $this->properties[$key] = (int)$value;
            break;

        case 'tid':
        case 'date':
        case 'title':
        case 'introtext':
        case 'bodytext':
            // Simple string values
            $this->properties[$key] = trim($value);
            break;

        case 'postmode':
            switch ($value) {
            case 'html':
            case 'text':
            case 'adveditor':
                $this->properties[$key] = $value;
                break;
            default:
                $this->properties[$key] = 'html';
                break;
            }
        }
    }


    /**
    *   Provide the blog editor
    *
    *   @return string  HTML for the editor form
    */
    function Edit()
    {
        global $_CONF, $_BLOG_CONF, $_TABLES, $_USER,
            $LANG_BLOG, $LANG24, $LANG_ADMIN, $LANG_ACCESS, $LANG12;

        $retval = '';
        $saveoption = $action;  // good for most actions

        $title = $LANG24[5];
        $lang_saveoption = $LANG_ADMIN['save'];
        $submission = false;
        $saveoption = 'save';

        if (!isset($_CONF['hour_mode'])) {
            $_CONF['hour_mode'] = 12;
        }

        $allowedTopicList = COM_topicList('tid,topic', $this->tid, 1, true);
        if ($allowedTopicList == '') {
            // User has no access to topics
            $display .= COM_startBlock($LANG_ACCESS['accessdenied'], '',
                                COM_getBlockTemplate('_msg_block', 'header'));
            $display .= $LANG24[42];
            $display .= COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
            COM_accessLog("User {$_USER['username']} tried to illegally access story $sid. No allowed topics.");
            return $display;
        }

        $T = new Template(BLOG_PI_PATH . '/templates');

        if ($_BLOG_CONF['adveditor'] > 0) {
            $advanced_editormode = true;
            USES_class_navbar();
            $T->set_file(array('editor' => 'submit_advanced.thtml'));
            if (file_exists($_CONF['path_layout'] . '/fckstyles.xml')) {
                $T->set_var('glfusionStyleBasePath', $_CONF['layout_url']);
            } else {
                $T->set_var('glfusionStyleBasePath', 
                        $_CONF['site_url'] . '/fckeditor');
            }
            $T->set_var(array(
                'change_editormode'     => 'onchange="change_editmode(this);"',
                'lang_expandhelp'       => $LANG24[67],
                'lang_reducehelp'       => $LANG24[68],
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
            $T->set_file(array('editor' => 'submit.thtml'));
            $advanced_editormode = false;
        }

        // start generating the story editor block
        if (!$this->isNew) {
            $delbutton = '<input type="submit" value="' . $LANG_ADMIN['delete']
                   . '" name="deletesubmission"' . XHTML . '>';
            $jsconfirm = ' onclick="return confirm(\'' . $MESSAGE[76] . '\');"';
            $T->set_var('delete_option', sprintf ($delbutton, $jsconfirm));
            $T->set_var('delete_option_no_confirmation', sprintf ($delbutton, ''));
        }
        $T->set_var('submission_option',
                    '<input type="hidden" name="type" value="submission"' . XHTML . '>');

        if ($this->author_id > 1) {
            $authorname = COM_getDisplayName($this->author_id);
            $lang_loginout = $LANG12[34];
            $status_url = $_CONF['site_url'].'/users.php?mode=logout';
            $ownername = COM_getDisplayName($this->owner_id);
            $allow_signup = '';
        } else {
            $status_url = $_CONF['site_url'] . '/users.php';
            $lang_loginout = $LANG12[2];
            $allow_signup = $_CONF['disable_new_user_registration'] ? '' : 'true';
            $authorname = '';
            $ownername = '';
        }

        $T->set_var(array(
            'status_url'    => $status_url,
            'authorname'    => $authorname,
            'ownername'     => $ownername,
            'allow_signup'  => $allow_signup,
            'lang_loginout' => $lang_loginout,
        ) );

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

        
        $T->set_var(array(
            'action_url'        => BLOG_URL . '/index.php',
            'pi_url'            => BLOG_URL,
            'date'              => $this->date,
            'author_name'       => COM_getDisplayName($this->owner_id),
            'author_id'         => $this->author_id,
            'owner_username'    => DB_getItem($_TABLES['users'], 'username', 
                                    'uid = ' . $this->author_id),
            'title'             => $this->title,
            'topic_options'     => $allowedTopicList,
            'show_topic_icon_checked' => $this->show_topic_icon == 1 ?
                                 BLOG_CHECKED : '',
            'introtext'         => $this->_editText($this->introtext),
            'bodytext'          => $this->_editText($this->bodytext),
            'no_javascript_return_link' => sprintf($LANG24[78],
                                    $_CONF['site_admin_url'], $sid),
            'post_options'      => $post_options,
            'allowed_html'      => COM_allowedHTML(),
            'story_id'          => $this->sid,
            'lang_save'         => $lang_saveoption,
            'saveoption'        => $saveoption,
            'gltoken_name'      => CSRF_TOKEN,
            'gltoken'           => SEC_createToken(),
        ) );

        if ($this->advanced_editor_mode == 1 || 
                $this->postmode == 'adveditor' ||
                $this->postmode == 'plaintext') {
            $T->set_var('show_allowedhtml', 'none');
        } else {
            $T->set_var('show_allowedhtml', '');
        }

        // TODO: submission form doesn't support images.  Need to set that up
        // and/or move all this to a new function
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

        $T->parse('output', 'editor');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;

    }   // function Edit()


    /**
    *   Save a blog submission.
    *
    *   @param  array   $A      Array of data, e.g. $_POST
    *   @return boolean         True on success, False on failure
    */
    function Save($A = array())
    {
        global $_TABLES, $_CONF, $_BLOG_CONF, $_USER;

        if (!empty($A)) {
            // Set all variables from $A, including updating the SID
            $this->setVars($A);
        }

        $this->author_id = $_USER['uid'];
        $this->sid = COM_makeSid();

        // Check for uploaded images to process
        if (count($_FILES) && $_BLOG_CONF['maximages'] > 0) {
            USES_blog_class_blogimage();
            $Image = new BlogImage($this->sid);
            $Image->uploadFiles();
            // TODO - error checking needed?
        }

        // Replace [imageX] tags in the story with actual image urls
        $this->insertImages();

        $sql = "INSERT INTO {$_TABLES['blog_submission']} SET 
            sid = '{$this->sid}',
            author_id = '{$this->author_id}',
            tid = '" . DB_escapeString($this->tid) . "',
            title = '" . DB_escapeString($this->title) . "',
            introtext = '" . DB_escapeString($this->introtext) . "',
            bodytext = '" . DB_escapeString($this->bodytext) . "',
            date = '" . DB_escapeString($this->date) . "',
            postmode = '" . DB_escapeString($this->postmode) . "'";

        //echo $sql;
        DB_query($sql, 1);
        if (DB_error()) {
            return false;
        } else {
            return true;
        }

    }   // function Save()


    /**
    *   Get the current object's properties.
    *   This is meant to allow a BlogItem object to read the Submission
    *   values during moderation.
    *
    *   @return array       $this->properties array
    */
    function Properties()
    {
        return $this->properties;
    }


    /**
    *   Delete the current blog record
    */
    function Delete()
    {
        global $_TABLES;

        // Ensure that we have a valid record, and the current user has
        // write access to the item.
        if ($this->sid == '' || !$this->isModerator) return false;

        DB_delete($_TABLES['article_images'], 'ai_sid', $this->sid);
        DB_delete($_TABLES['blog_submission'], 'sid', $this->sid);
        $this->sid = '';
        return true;

    }   // function Delete()


}   // class BlogSubmission

?>
