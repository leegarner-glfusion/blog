<?php
//  $Id: BlogImage.class.php 8 2011-03-14 21:44:52Z root $
/**
*   Class to handle images
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    blog
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

USES_class_upload();

/**
 *  Image-handling class
 *  @package blog
 */
class BlogImage extends upload
{
    /** Path to actual image (without filename)
     *  @var string */
    var $pathImage;

    /** ID of the current article
     *  @var string */
    var $sid;

    var $varname;


    /**
     *  Constructor
     *  @param string $name Optional image filename
     */
    function BlogImage($sid, $varname='file')
    {
        global $_CONF;

        // Call the parent constructor to initialize
        parent::upload();

        $this->sid = COM_sanitizeID($sid);
        $this->varname = $varname;
        $this->pathImage = $_CONF['path_images'] . 'articles';
    }


    /**
    *   Perform the upload.
    *   Make sure we can upload the files before adding the image to the 
    *   database.
    *
    *   The caller can check the _errors array for the cause of failure.
    *
    *   @return boolean     True on complete success, false on first failure
    */
    function uploadFiles()
    {
        global $_CONF, $_BLOG_CONF, $_TABLES;

        // If there are no files to upload, don't bother doing anything. This
        // isn't really an error, so return "true"
        if (empty($_FILES[$this->varname]['name'])) {
            return true;
        }

        // Before anything else, check the upload directory
        if (!$this->setPath($this->pathImage)) {
            return false;
        }

        $this->setContinueOnError(true);
        if ($_CONF['debug_image_upload']) {
            $this->setLogFile($_CONF['path'] . 'logs/error.log');
            $this->setDebug(true);
        }

        if ($_CONF['keep_unscaled_image'] == 1) {
            $this->keepOriginalImage(true);
        } else {
            $this->keepOriginalImage(false);
        }

        $this->setAllowedMimeTypes(array (
                    'image/gif'   => '.gif',
                    'image/jpeg'  => '.jpg,.jpeg',
                    'image/pjpeg' => '.jpg,.jpeg',
                    'image/x-png' => '.png',
                    'image/png'   => '.png'
        ) );

        $this->setAllowedMimeTypes(array(
                'image/pjpeg' => '.jpg,.jpeg',
                'image/jpeg'  => '.jpg,.jpeg',
                'image/png'   => '.png',
        ));
        $this->setMaxFileUploads($_CONF['maximagesperarticle']);
        $this->setAutomaticResize(true);
        $this->setFieldName($this->varname);
        $this->setMaxFileSize($_CONF['max_image_size']);
        $this->setMaxDimensions(
                $_CONF['img_max_width'], 
                $_CONF['img_max_height']
        );
        $this->setPerms('0644');

        // Get the next file number to be used
        $sql = "SELECT MAX(ai_img_num) + 1 AS ai_img_num 
                FROM {$_TABLES['article_images']} 
                WHERE ai_sid = '" . DB_escapeString($this->sid) ."'";
        $result = DB_query($sql, 1);
        $row = DB_fetchArray($result);
        $ai_img_num = $row['ai_img_num'];
        if ($ai_img_num < 1) {
            $ai_img_num = 1;
        }

        $upload_count = count($_FILES[$varname]['name']) + $ai_img_num;
        $filenames = array();

        // This array is to keep track of filespec=>filenumbers, since the
        // only thing we get back from parent::upload() is an array of uploaded
        // filespecs.
        $filenumbers = array();

        for ($i = 0;
                $i <= $upload_count - $ai_img_num && 
                $i <= $_BLOG_CONF['maximages'];
                $i++) {
            $fname = $_FILES[$this->varname]['name'][$i];
            if (empty($fname)) break;   // reached end of uploaded files
            $pos = strrpos($fname,'.') + 1;
            $fextension = substr($fname, $pos);
            $img_num = $i + $ai_img_num;
            $filename = "{$this->sid}_{$img_num}.{$fextension}";
            $filenames[] = $filename;
            $filenumbers[$this->pathImage.'/'.$filename] = 
                        $i + $ai_img_num;
        }

        if (!empty($filenames)) {
            $this->setFileNames($filenames);
        }

        // Call the parent to actually perform the upload and set values
        // in the _uploadedFiles array
        parent::uploadFiles();

        if ($this->areErrors()) {
            return false;
        }

        $path_len = strlen($this->pathImage.'/');
        $DB_sid = DB_escapeString($this->sid);
        foreach ($this->_uploadedFiles as $filespec) {
            $filename = substr($filespec, $path_len);
            $sql = "
                INSERT INTO {$_TABLES['article_images']} 
                    (ai_sid, ai_img_num, ai_filename)
                VALUES ('$DB_sid', {$filenumbers[$filespec]}, 
                    '" . DB_escapeString($filename) . "')";
            $result = DB_query($sql);
            if (!$result) {
                $this->addError("uploadFiles() : Failed to insert {$filename}");
                return false;
            }
        }
        return true;
    }


    /**
     *  Delete an image from disk.  Called by Entry::Delete if disk
     *  deletion is requested.
     */
    function Delete($id = 0)
    {
        global $_TABLES, $_USER, $_BLOG_CONF;

        $filenames = array();

        $sql = "SELECT ai_filename
                FROM {$_TABLES['article_images']}
                WHERE ai_sid = '" . DB_escapeString($this->sid) . "'";
        if ($id > 0) {
            $sql .= " AND ai_img_num = '" . (int)$id . "'";
        }
        $res = DB_query($sql, 1);
        while ($A = DB_fetchArray($res, false)) {
            $filespec = $this->pathImage . '/' . $A['ai_filename'];
            if (file_exists($filespec))
                unlink($filespec);
        }
 
    }

}   // class BlogImage

?>
