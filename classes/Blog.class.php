<?php
//  $Id: Blog.class.php 18 2011-04-04 15:59:00Z root $
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


/**
*   Base class for blog items and submissions
*   @package blog
*/
class Blog
{
    var $mode = 'admin';

    protected $properties;
    protected $isAdmin;
    protected $isEditor;
    protected $isModerator;
    protected $isNew;
    protected $table;         // Table to use (blogs or blog_submission)

    /**
     * The access level.
     */
    protected $_access;

    /**
     * Array of images uploaded for the story.
     */
    protected $_storyImages;
    protected $error;             // Error status


    /**
    *   Constructor, creates a story, taking a (glFusion) database object.
    *
    *   @param  string  $sid    Article ID to retrieve, empty if new.
    *   @param  string  $mode   Story class mode, either 'admin' or 'submission'
    */
    function __construct($sid = '', $mode = 'admin')
    {
        global $_USER, $_BLOG_CONF, $_CONF;

        $this->isAdmin = SEC_hasRights('blog.admin') ? true : false;
        $this->isEditor = $this->isAdmin ||
                            SEC_hasRights('blog.edit') ? true : false;
        $this->isModerator = $this->isAdmin || plugin_ismoderator_blog();

        $this->isNew = true;
        $this->mode = $mode;
        $this->properties = array();
        $this->setDefaults();
    }


    /**
    *   Magic function to read a property.
    *
    *   @param  string  $key    Name of the property
    *   @return mixed           Value of the property, or NULL if nonexistent
    */
    function __get($key)
    {
        if (isset($this->properties[$key])) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    /**
     *  Check to see if there is any content in the story.
     *
     *  @return boolean     True if there's any content, False if empty
     */
    function hasContent()
    {
        if ($this->title != '' ||
            $this->introtext != '' ||
            $this->_bodytext != '') {
            return true;
        }

        return false;
    }


    /**
     * Inserts image HTML into the place of Image Placeholders for stories
     * with images.
     *
     * @return array    containing errors, or empty.
     */
    function insertImages()
    {
        global $_CONF, $_TABLES, $LANG24;

        // Grab member vars into locals:
        $intro = $this->introtext;
        $body = $this->bodytext;
        $fulltext = "$intro $body";

        $result = DB_query("SELECT ai_filename 
                FROM {$_TABLES['article_images']} WHERE
                ai_sid = '{$this->sid}'
                ORDER BY ai_img_num");
        $nrows = DB_numRows($result);
        $errors = array();
        $stdImageLoc = true;

        if (!strstr($_CONF['path_images'], $_CONF['path_html'])) {
            $stdImageLoc = false;
        }

        for ($i = 1; $i <= $nrows; $i++) {
            $A = DB_fetchArray($result, false);

            $sizeattributes = COM_getImgSizeAttributes(
                    $_CONF['path_images'] . 'articles/' . $A['ai_filename']);

            $norm = '[image' . $i . ']';
            $left = '[image' . $i . '_left]';
            $right = '[image' . $i . '_right]';

            $unscalednorm = '[unscaled' . $i . ']';
            $unscaledleft = '[unscaled' . $i . '_left]';
            $unscaledright = '[unscaled' . $i . '_right]';

            // See how many times image $i is used in the fulltext of the blog:
            $icount = substr_count($fulltext, $norm) + 
                        substr_count($fulltext, $left) +
                        substr_count($fulltext, $right);
            // including unscaled.
            $icount += substr_count($fulltext, $unscalednorm) +
                        substr_count($fulltext, $unscaledleft) +
                        substr_count($fulltext, $unscaledright);

            // If the image we are currently looking at wasn't used, we need
            // to log an error
            if ($icount == 0) {
                // There is an image that wasn't used, create an error
                $errors[] = $LANG24[48] . 
                            " #$i, {$A['ai_filename']}, " . 
                            $LANG24[53];
            } else {
                // We had no errors, so this image and all previous images
                // are used, so we will then go and replace them
                if (count($errors) == 0) {

                    $imgpath = '';

                    // If we are storing images on a "standard path" i.e. is
                    // available to the host web server, then the url to this
                    // image is based on the path to images, site url, blogs
                    // folder and it's filename.
                    //
                    // Otherwise, we have to use the image handler to load the
                    // image from whereever else on the file system we're
                    // keeping them:
                    if ($stdImageLoc) {
                        $imgpath = substr($_CONF['path_images'], 
                                    strlen($_CONF['path_html']));
                        $imgSrc = $_CONF['site_url'] . '/' . $imgpath . 
                                    'articles/' . $A['ai_filename'];
                    } else {
                        $imgSrc = $_CONF['site_url'] . 
                                    '/getimage.php?mode=articles&amp;image=' . 
                                    $A['ai_filename'];
                    }

                    // Build image tags for each flavour of the image:
                    $img_noalign = '<img ' . $sizeattributes . 
                                'src="' . $imgSrc . '" alt=""' . XHTML . '>';
                    $img_leftalgn = '<img ' . $sizeattributes . 
                                'class="floatleft" src="' . $imgSrc . 
                                '" alt=""' . XHTML . '>';
                    $img_rightalgn = '<img ' . $sizeattributes . 
                                'class="floatright" src="' . $imgSrc . 
                                '" alt=""' . XHTML . '>';

                    // Are we keeping unscaled images?
                    if ($_CONF['keep_unscaled_image'] == 1) {
                        // Yes we are, so, we need to find out what the filename
                        // of the original, unscaled image is:
                        $lFilename_large = substr_replace($A['ai_filename'], 
                                    '_original.',
                                    strrpos($A['ai_filename'], '.'), 1);
                        $lFilename_large_complete = $_CONF['path_images'] . 
                                    'articles/' . $lFilename_large;

                        // We need to map that filename to the right location
                        // or the fetch script:
                        if ($stdImageLoc) {
                            $lFilename_large_URL = $_CONF['site_url'] . '/' . 
                                    $imgpath . 'articles/' . 
                                    $lFilename_large;
                        } else {
                            $lFilename_large_URL = $_CONF['site_url'] .
                                    '/getimage.php?mode=show&amp;image=' .
                                    $lFilename_large;
                        }

                        // And finally, replace the [imageX_mode] tags with the
                        // image and its hyperlink (only when the large image
                        // actually exists)
                        $lLink_url  = '';
                        $lLink_attr = '';
                        if (file_exists($lFilename_large_complete)) {
                            $lLink_url = $lFilename_large_URL;
                            $lLink_attr = array('title' => $LANG24[57]);
                        }
                    }

                    if (!empty($lLink_url)) {
                        $intro = str_replace($norm,  
                                COM_createLink($img_noalign, $lLink_url, 
                                    $lLink_attr), $intro);
                        $body  = str_replace($norm,
                                COM_createLink($img_noalign,
                                    $lLink_url, $lLink_attr), $body);
                        $intro = str_replace($left,
                                COM_createLink($img_leftalgn,
                                    $lLink_url, $lLink_attr), $intro);
                        $body  = str_replace($left,
                                COM_createLink($img_leftalgn,
                                    $lLink_url, $lLink_attr), $body);
                        $intro = str_replace($right,
                                COM_createLink($img_rightalgn, 
                                    $lLink_url, $lLink_attr), $intro);
                        $body  = str_replace($right,
                                COM_createLink($img_rightalgn,
                                    $lLink_url, $lLink_attr), $body);
                    } else {
                        // We aren't wrapping our image tags in hyperlinks, so
                        // just replace the [imagex_mode] tags with the image:
                        $intro = str_replace($norm,  $img_noalign,   $intro);
                        $body  = str_replace($norm,  $img_noalign,   $body);
                        $intro = str_replace($left,  $img_leftalgn,  $intro);
                        $body  = str_replace($left,  $img_leftalgn,  $body);
                        $intro = str_replace($right, $img_rightalgn, $intro);
                        $body  = str_replace($right, $img_rightalgn, $body);
                    }

                    // And insert the unscaled mode images:
                    if ($_CONF['allow_user_scaling'] == 1 && 
                            $_CONF['keep_unscaled_image'] == 1) {
                        if (file_exists($lFilename_large_complete)) {
                            $imgSrc = $lFilename_large_URL;
                            $sizeattributes = COM_getImgSizeAttributes(
                                        $lFilename_large_complete);
                        }

                        $intro = str_replace($unscalednorm, 
                                '<img ' . $sizeattributes . 'src="' .
                                $imgSrc . '" alt=""' . XHTML . '>', $intro);
                        $body  = str_replace($unscalednorm, 
                                '<img ' . $sizeattributes . 'src="' .
                                $imgSrc . '" alt=""' . XHTML . '>', $body);
                        $intro = str_replace($unscaledleft, 
                                '<img ' . $sizeattributes .
                                'align="left" src="' . $imgSrc . 
                                '" alt=""' . XHTML . '>', $intro);
                        $body  = str_replace($unscaledleft, 
                                '<img ' . $sizeattributes .
                                'align="left" src="' . $imgSrc . 
                                '" alt=""' . XHTML . '>', $body);
                        $intro = str_replace($unscaledright, 
                                '<img ' . $sizeattributes .
                                'align="right" src="' . $imgSrc. 
                                '" alt=""' . XHTML . '>', $intro);
                        $body  = str_replace($unscaledright, 
                                '<img ' . $sizeattributes .
                                'align="right" src="' . $imgSrc . 
                                '" alt=""' . XHTML . '>', $body);
                    }
                }
            }
        }

        $this->introtext = $intro;
        $this->bodytext  = $body;

        return $errors;
    }


    /**
     * This replaces all blog image HTML in intro and body with
     * GL special syntax
     *
     * @param    string      $sid    ID for story to parse
     * @param    string      $intro  Intro text
     * @param    string      $body   Body text
     * @return   string      processed text
     *
     */
    function replaceImages($text)
    {
        global $_CONF, $_TABLES, $LANG24;

        $stdImageLoc = true;

        if (!strstr($_CONF['path_images'], $_CONF['path_html'])) {
            $stdImageLoc = false;
        }

        $count = 0;
        // If we haven't already cached the images for this story, do so
        if (!is_array($this->_storyImages)) {
            $result= DB_query("SELECT ai_filename
                        FROM {$_TABLES['article_images']}
                        WHERE ai_sid = '{$this->sid}'
                        ORDER BY ai_img_num");
            $nrows = DB_numRows($result);
            $this->_storyImages = array();

            for ($i = 1; $i <= $nrows; $i++) {
                $this->_storyImages[] = DB_fetchArray($result, false);
            }
            $count = $nrows;
        } else {
            $count = count($this->_storyImages);
        }

        // If the blog has any images, remove them back to [image] tags.
        for ($i = 0; $i < $count; $i++) {
            $A = $this->_storyImages[$i];

            $imageX = '[image' . ($i + 1) . ']';
            $imageX_left = '[image' . ($i + 1) . '_left]';
            $imageX_right = '[image' . ($i + 1) . '_right]';

            $sizeattributes = COM_getImgSizeAttributes($_CONF['path_images'] .
                    'articles/' . $A['ai_filename']);

            $lLinkPrefix = '';
            $lLinkSuffix = '';

            if ($_CONF['keep_unscaled_image'] == 1) {
                $lFilename_large = substr_replace($A['ai_filename'],
                        '_original.', strrpos($A['ai_filename'], '.'), 1);
                $lFilename_large_complete = $_CONF['path_images'] .
                        'articles/' . $lFilename_large;
                        'articles/' . $lFilename_large;

                if ($stdImageLoc) {
                    $imgpath = substr($_CONF['path_images'],
                            strlen($_CONF['path_html']));
                    $lFilename_large_URL = $_CONF['site_url'] . '/' .
                            $imgpath . 'articles/' . $lFilename_large;
                } else {
                    $lFilename_large_URL = $_CONF['site_url'] .
                            '/getimage.php?mode=show&amp;image=' .
                            $lFilename_large;
                }

                if (file_exists($lFilename_large_complete)) {
                    $lLinkPrefix = '<a href="' . $lFilename_large_URL .
                            '" title="' . $LANG24[57] . '">';
                    $lLinkSuffix = '</a>';
                }
            }

            if ($stdImageLoc) {
                $imgpath = substr($_CONF['path_images'],
                            strlen($_CONF['path_html']));
                $imgSrc = $_CONF['site_url'] . '/' . $imgpath . 'articles/' .
                            $A['ai_filename'];
            } else {
                $imgSrc = $_CONF['site_url'] .
                            '/getimage.php?mode=blogs&amp;image=' .
                            $A['ai_filename'];
            }

            $norm = $lLinkPrefix . '<img ' . $sizeattributes .
                        'src="' . $imgSrc . '" alt=""' . XHTML . '>' .
                        $lLinkSuffix;
            $left = $lLinkPrefix . '<img ' . $sizeattributes .
                        'class="floatleft" src="' . $imgSrc .
                        '" alt=""' . XHTML . '>' .
                        $lLinkSuffix;
            $right = $lLinkPrefix . '<img ' . $sizeattributes .
                        'class="floatright" src="' . $imgSrc .
                        '" alt=""' . XHTML . '>' .
                        $lLinkSuffix;

            $text = str_replace($norm, $imageX, $text);
            $text = str_replace($left, $imageX_left, $text);
            $text = str_replace($right, $imageX_right, $text);

            if ($_CONF['allow_user_scaling'] == 1 &&
                    $_CONF['keep_unscaled_image'] == 1) {
                $unscaledX = '[unscaled' . ($i + 1) . ']';
                $unscaledX_left = '[unscaled' . ($i + 1) . '_left]';
                $unscaledX_right = '[unscaled' . ($i + 1) . '_right]';

                if (file_exists($lFilename_large_complete)) {
                    $sizeattributes = COM_getImgSizeAttributes(
                                $lFilename_large_complete);
                    $norm = '<img ' . $sizeattributes . 'src="' .
                            $lFilename_large_URL . '" alt=""' . XHTML . '>';
                   $left = '<img ' . $sizeattributes . 'align="left" src="' .
                            $lFilename_large_URL . '" alt=""' . XHTML . '>';
                    $right = '<img ' . $sizeattributes . 'align="right" src="' .
                            $lFilename_large_URL . '" alt=""' . XHTML . '>';
                }

                $text = str_replace($norm, $unscaledX, $text);
                $text = str_replace($left, $unscaledX_left, $text);
                $text = str_replace($right, $unscaledX_right, $text);
            }
        }

        return $text;
    }


    /**
     * Escapes certain HTML for nicely encoded HTML.
     *
     * @access Private
     * @param   string     $in      Text to escpae
     * @return  string     escaped string
     */
    function _displayEscape($in)
    {
        return $in;
        $return = str_replace('$', '&#36;', $in);
        $return = str_replace('{', '&#123;', $return);
        $return = str_replace('}', '&#125;', $return);
        return $return;
    }

    /**
     * Unescapes certain HTML for editing again.
     *
     * @access Private
     * @param   string  $in Text escaped to unescape for editing
     * @return  string  Unescaped string
     */
    function _editUnescape($in)
    {
        if (($this->_postmode == 'html') || ($this->_postmode == 'wikitext')) {
            /* Raw and code blocks need entity decoding. Other areas do not.
             * otherwise, annoyingly, &lt; will end up as < on preview 1, on
             * preview 2 it'll be stripped by KSES. Can't beleive I missed that
             * in rewrite phase 1.
             *
             * First, raw
             */
            $inlower = MBYTE_strtolower($in);
            $buffer = $in;
            $start_pos = MBYTE_strpos($inlower, '[raw]');
            if( $start_pos !== false ) {
                $out = '';
                while( $start_pos !== false ) {
                    /* Copy in to start to out */
                    $out .= MBYTE_substr($buffer, 0, $start_pos);
                    /* Find end */
                    $end_pos = MBYTE_strpos($inlower, '[/raw]');
                    if( $end_pos !== false ) {
                        /* Encode body and append to out */
                        $encoded = html_entity_decode(MBYTE_substr($buffer, $start_pos, $end_pos - $start_pos));
                        $out .= $encoded . '[/raw]';
                        /* Nibble in */
                        $inlower = MBYTE_substr($inlower, $end_pos + 6);
                        $buffer = MBYTE_substr($buffer, $end_pos + 6);
                    } else { // missing [/raw]
                        // Treat the remainder as code, but this should have been
                        // checked prior to calling:
                        $out .= html_entity_decode(MBYTE_substr($buffer, $start_pos + 5));
                        $inlower = '';
                    }
                    $start_pos = MBYTE_strpos($inlower, '[raw]');
                }
                // Append remainder:
                if( $buffer != '' ) {
                    $out .= $buffer;
                }
                $in = $out;
            }
            /*
             * Then, code
             */
            $inlower = MBYTE_strtolower($in);
            $buffer = $in;
            $start_pos = MBYTE_strpos($inlower, '[code]');
            if( $start_pos !== false ) {
                $out = '';
                while( $start_pos !== false ) {
                    /* Copy in to start to out */
                    $out .= MBYTE_substr($buffer, 0, $start_pos);
                    /* Find end */
                    $end_pos = MBYTE_strpos($inlower, '[/code]');
                    if( $end_pos !== false ) {
                        /* Encode body and append to out */
                        $encoded = html_entity_decode(MBYTE_substr($buffer, $start_pos, $end_pos - $start_pos));
                        $out .= $encoded . '[/code]';
                        /* Nibble in */
                        $inlower = MBYTE_substr($inlower, $end_pos + 7);
                        $buffer = MBYTE_substr($buffer, $end_pos + 7);
                    } else { // missing [/code]
                        // Treat the remainder as code, but this should have been
                        // checked prior to calling:
                        $out .= html_entity_decode(MBYTE_substr($buffer, $start_pos + 6));
                        $inlower = '';
                    }
                    $start_pos = MBYTE_strpos($inlower, '[code]');
                }
                // Append remainder:
                if( $buffer != '' ) {
                    $out .= $buffer;
                }
                $in = $out;
            }
            return $in;
        } else {
            // advanced editor or plaintext can handle themselves...
            return $in;
        }
    }

    /**
     * Returns text ready for the edit fields.
     *
     * @access Private
     * @param   string  $in Text to prepare for editing
     * @return  string  Escaped String
     */
    function _editText($in)
    {
        $out = '';

        $out = $this->replaceImages($in);

        if ($this->_postmode == 'plaintext') {
            $out = COM_undoClickableLinks($out);
            $out = $this->_displayEscape($out);
        } elseif ($this->_postmode == 'wikitext') {
            $out = $this->_editUnescape($in);
        } else {
            // html
            $out = str_replace('<pre><code>', '[code]', $out);
            $out = str_replace('</code></pre>', '[/code]', $out);
            $out = str_replace('<!--raw--><span class="raw">', '[raw]', $out);
            $out = str_replace('</span><!--/raw-->', '[/raw]', $out);
            $out = $this->_editUnescape($out);
            $out = $this->_displayEscape(htmlspecialchars($out));
        }

        return $out;
    }   // function _editText()


    /**
    *   Delete a single image file.
    *   Does not update the database
    *
    *   @param  string  $image  Image filename, no path
    */
    function deleteImage($image)
    {
        global $_CONF;

        if (empty ($image)) {
            return;
        }

        $filename = $_CONF['path_images'] . 'articles/' . $image;
        if (!@unlink($filename)) {
            // log the problem but don't abort the script
            COM_errorLog('Unable to remove the image from the article: ' . 
                    $filename);
        }

        // remove unscaled image, if it exists
        $lFilename_large = substr_replace($image, '_original.',
                                       strrpos($image, '.'), 1);
        $lFilename_large_complete = $_CONF['path_images'] . 'articles/'
                              . $lFilename_large;
        if (file_exists($lFilename_large_complete)) {
            if (!@unlink($lFilename_large_complete)) {
                // again, log the problem but don't abort the script
                COM_errorLog('Unable to remove the  image from the article: ' .
                     $lFilename_large_complete);
            }
        }
    }


    /**
    *   Delete all images from a blog entry
    *   Accepts an ID value to be called as "Blog::deleteImages('xyz')"
    *   or operates on the current object
    *
    *   @param  string  $sid    Optional ID, current object ID used if empty
    */
    function deleteImages($sid = '')
    {
        global $_TABLES;

        if ($sid == '' && is_object($this)) {
            $sid = $this->sid;
        }
        if (empty($sid)) {
            return '';
        } else {
            $sid = DB_escapeString($sid);
        }

        $result = DB_query("SELECT ai_filename 
                    FROM {$_TABLES['article_images']} 
                    WHERE ai_sid = '$sid'");
        while ($A = DB_fetchArray($result, false)) {
            Blog::deleteImage($A['ai_filename']);
        }
        DB_delete($_TABLES['article_images'], 'ai_sid', $sid);
    }


}   // class Blog

?>
