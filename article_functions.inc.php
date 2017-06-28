<?php
// +--------------------------------------------------------------------------+
// | glFusion CMS                                                             |
// +--------------------------------------------------------------------------+
// | lib-story.php                                                            |
// |                                                                          |
// | Story-related functions needed in more than one place.                   |
// +--------------------------------------------------------------------------+
// | $Id:: lib-story.php 5975 2010-04-19 00:29:56Z usableweb                 $|
// +--------------------------------------------------------------------------+
// | Copyright (C) 2008-2010 by the following authors:                        |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// | Mark Howard            mark AT usable-web DOT com                        |
// |                                                                          |
// | Based on the Geeklog CMS                                                 |
// | Copyright (C) 2000-2008 by the following authors:                        |
// |                                                                          |
// | Authors: Tony Bibbs        - tony AT tonybibbs DOT com                   |
// |          Mark Limburg      - mlimburg AT users DOT sourceforge DOT net   |
// |          Jason Whittenburg - jwhitten AT securitygeeks DOT com           |
// |          Dirk Haun         - dirk AT haun-online DOT de                  |
// |          Vincent Furia     - vinny01 AT users DOT sourceforge DOT net    |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}

USES_article_class_article();
global $_CONF;

/* Check for PHP5 */
if (PHP_VERSION < 5) {
    $_CONF['disable_webservices'] = true;
} else {
    require_once $_CONF['path_system'] . '/lib-webservices.php';
}

if ($_CONF['allow_user_photo']) {
    // only needed for the USER_getPhoto function
    USES_lib_user();
}

// this must be kept in sync with the actual size of 'sid' in the db ...
define('ARTICLE_MAX_ID_LENGTH', 40);
// Story Record Options for the STATUS Field
if (!defined ('ARTICLE_ARCHIVE_ON_EXPIRE')) {
    define ('ARTICLE_ARCHIVE_ON_EXPIRE', '10');
    define ('ARTICLE_DELETE_ON_EXPIRE',  '11');
}
/**
 * Takes an article class and renders HTML in the specified template and style.
 *
 * Formats the given article into HTML. Called by index.php, article.php,
 * submit.php and admin/story.php (Preview mode for the last two).
 *
 * @param   object  $story      The story to display, an instance of the Story class.
 * @param   string  $index      n = 'Compact display' for list of stories. p = 'Preview' mode. Else full display of article.
 * @param   string  $storytpl   The template to use to render the story.
 * @param   string  $query      A search query, if one was specified.
 *
 * @return  string  Article as formated HTML.
 *
 * Note: Formerly named COM_Article, and re-written totally since then.
 */
function ARTICLE_renderArticle( &$story, $index='', $storytpl='storytext.thtml', $query='')
{
    global $_CONF, $_TABLES, $_USER, $LANG01, $LANG05, $LANG11, $LANG_TRB,
           $_IMAGE_TYPE, $mode, $_GROUPS, $ratedIds;

    static $storycounter = 0;

    if( empty( $storytpl )) {
        $storytpl = 'storytext.thtml';
    }

    $introtext = $story->displayElements('introtext');
    $bodytext = $story->displayElements('bodytext');

    if( !empty( $query )) {
        $introtext = COM_highlightQuery( $introtext, $query );
        $bodytext  = COM_highlightQuery( $bodytext, $query );
    }

    $article = new Template( $_CONF['path_layout'] );
    $article->set_file( array(
            'article'          => $storytpl,
            'bodytext'         => 'storybodytext.thtml',
            'featuredarticle'  => 'featuredstorytext.thtml',
            'featuredbodytext' => 'featuredstorybodytext.thtml',
            'archivearticle'   => 'archivestorytext.thtml',
            'archivebodytext'  => 'archivestorybodytext.thtml'
            ));

    if( $_CONF['hideviewscount'] != 1 ) {
        $article->set_var( 'lang_views', $LANG01[106] );
        $article->set_var( 'story_hits', $story->DisplayElements('hits'),false,true );
    }

    if ( $_CONF['hidestorydate'] != 1 ) {
        $article->set_var( 'story_date', $story->DisplayElements('date'), false, true); // make sure date format is in user's preferred format
    }
    $articleUrl = COM_buildUrl($_CONF['site_url'] . '/article.php?story='
                                . $story->getSid());

    $article->set_var( 'article_url', $articleUrl );
    $article->set_var('story_title', $story->DisplayElements('title'));

    // begin instance caching...
    if( $story->DisplayElements('featured') == 1 ) {
        $article_filevar = 'featuredarticle';
    } elseif( $story->DisplayElements('statuscode') == ARTICLE_ARCHIVE_ON_EXPIRE AND $story->DisplayElements('expire') <= time() ) {
        $article_filevar = 'archivearticle';
    } else {
        $article_filevar = 'article';
    }

    $hash = CACHE_security_hash();
    $instance_id = 'story_'.$story->getSid().'_'.$index.$mode.'_'.$article_filevar.'_'.$hash.'_'.$_CONF['theme'];

    if ( $index == 'p' || !empty($query) || !$article->check_instance($instance_id,$article_filevar)) {
    // end of instance cache
        $article->set_var('article_filevar','');
        $article->set_var( 'xhtml', XHTML );
        $article->set_var( 'layout_url', $_CONF['layout_url'] );
        $article->set_var( 'site_url', $_CONF['site_url'] );
        $article->set_var( 'site_admin_url', $_CONF['site_admin_url'] );
        $article->set_var( 'site_name', $_CONF['site_name'] );
        if ( $_CONF['hidestorydate'] != 1 ) {
            $article->set_var( 'story_date_short', $story->DisplayElements('shortdate') );
            $article->set_var( 'story_date_only', $story->DisplayElements('dateonly') );
        }

        $article->set_var( 'story_id', $story->getSid() );

        if ($_CONF['contributedbyline'] == 1) {
            $article->set_var('lang_contributed_by', $LANG01[1]);
            $article->set_var('contributedby_uid', $story->DisplayElements('uid'));
            $fullname = $story->DisplayElements('fullname');
            $username = $story->DisplayElements('username');
            $article->set_var('contributedby_user', $username);
            if (empty($fullname)) {
                $article->set_var('contributedby_fullname', $username);
            } else {
                $article->set_var('contributedby_fullname',$fullname);
            }
            $authorname = COM_getDisplayName( $story->DisplayElements('uid'),$username, $fullname);

            $article->set_var( 'author', $authorname );
            $profileUrl = $_CONF['site_url'] . '/users.php?mode=profile&amp;uid='
                . $story->DisplayElements('uid');

            if( $story->DisplayElements('uid') > 1 ) {
                $article->set_var( 'contributedby_url', $profileUrl );
                $authorname = COM_createLink($authorname, $profileUrl, array('class' => 'storybyline'));
            }
            $article->set_var( 'contributedby_author', $authorname );

            $photo = '';
            if ($_CONF['allow_user_photo'] == 1) {
                $authphoto = $story->DisplayElements('photo');
                if (empty($authphoto)) {
                    $authphoto = '(none)'; // user does not have a photo
                }
                $photo = USER_getPhoto($story->DisplayElements('uid'), $authphoto,
                                       $story->DisplayElements('email'));
            }
            if (!empty($photo)) {
                $article->set_var('contributedby_photo', $photo);
                $article->set_var('author_photo', $photo);
                $camera_icon = '<img src="' . $_CONF['layout_url']
                             . '/images/smallcamera.' . $_IMAGE_TYPE . '" alt=""'
                             . XHTML . '>';
                $article->set_var('camera_icon',
                                  COM_createLink($camera_icon, $profileUrl));
            } else {
                $article->set_var ('contributedby_photo', '');
                $article->set_var ('author_photo', '');
                $article->set_var ('camera_icon', '');
            }
        }

        $topicname = $story->DisplayElements('topic');
        $article->set_var('story_topic_id', $story->DisplayElements('tid'));
        $article->set_var('story_topic_name', $topicname);

        $topicurl = $_CONF['site_url'] . '/index.php?topic=' . $story->DisplayElements('tid');
        if(( !isset( $_USER['noicons'] ) OR ( $_USER['noicons'] != 1 )) AND
                $story->DisplayElements('show_topic_icon') == 1 ) {
            $imageurl = $story->DisplayElements('imageurl');
            if( !empty( $imageurl )) {
                $imageurl = COM_getTopicImageUrl( $imageurl );
                $article->set_var( 'story_topic_image_url', $imageurl );
                $topicimage = '<img src="' . $imageurl . '" class="float'
                            . $_CONF['article_image_align'] . '" alt="'
                            . $topicname . '" title="' . $topicname . '"' . XHTML . '>';
                $article->set_var( 'story_anchortag_and_image',
                    COM_createLink(
                        $topicimage,
                        $topicurl,
                        array('rel'=>"category tag")
                    )
                );
                $article->set_var( 'story_topic_image', $topicimage );
                $topicimage_noalign = '<img src="' . $imageurl . '" alt="'
                            . $topicname . '" title="' . $topicname . '"' . XHTML . '>';
                $article->set_var( 'story_anchortag_and_image_no_align',
                    COM_createLink(
                        $topicimage_noalign,
                        $topicurl,
                        array('rel'=>"category tag")
                    )
                );
                $article->set_var( 'story_topic_image_no_align',
                                   $topicimage_noalign );
            }
        }
        $article->set_var( 'story_topic_url', $topicurl );

        $recent_post_anchortag = '';
        $articleUrl = COM_buildUrl($_CONF['site_url'] . '/article.php?story='
                                    . $story->getSid());
        $article->set_var('story_title', $story->DisplayElements('title'));
        $article->set_var('lang_permalink', $LANG01[127]);

        $show_comments = true;

        // n = 'Compact display' for list of stories. p = 'Preview' mode.

        if ((($index != 'n') && ($index != 'p')) || !empty($query)) {
            $attributes = ' class="non-ul"';
            $attr_array = array('class' => 'non-ul');
            if (!empty($query)) {
                $attributes .= ' rel="bookmark"';
                $attr_array['rel'] = 'bookmark';
            }
            $article->set_var('start_storylink_anchortag',
                              '<a href="' . $articleUrl . '"' . $attributes . '>');
            $article->set_var('end_storylink_anchortag', '</a>');
            $article->set_var('story_title_link',
                COM_createLink(
                        $story->DisplayElements('title'),
                        $articleUrl,
                        $attr_array
                )
            );
        } else {
            $article->set_var('story_title_link', $story->DisplayElements('title'));
        }

        if(( $index == 'n' ) || ( $index == 'p' )) {
            if( empty( $bodytext )) {
                $article->set_var( 'story_introtext', $introtext );
                $article->set_var( 'story_text_no_br', $introtext );
            } else {
                if(( $_CONF['allow_page_breaks'] == 1 ) and ( $index == 'n' )) {
                    $story_page = 1;

                    // page selector
                    if( is_numeric( $mode )) {
                        $story_page = $mode;
                        if( $story_page <= 0 ) {
                            $story_page = 1;
                            $mode = 0;
                        } elseif( $story_page > 1 ) {
                            $introtext = '';
                        }
                    }
                    $article_array = explode( '[page_break]', $bodytext );
                    $pagelinks = COM_printPageNavigation(
                        $articleUrl, $story_page, count( $article_array ),
                        'mode=', $_CONF['url_rewrite'], $LANG01[118]);
                    if( count( $article_array ) > 1 ) {
                        $bodytext = $article_array[$story_page - 1];
                    }
                    $article->set_var( 'page_selector', $pagelinks );

                    if (
                         ( ($_CONF['page_break_comments'] == 'last')  and
                           ($story_page < count($article_array)) )
                        or
                         ( ($_CONF['page_break_comments'] == 'first')  and
                           ($story_page != 1) )
                       )
                    {
                        $show_comments = false;
                    }
                    $article->set_var( 'story_page', $story_page );
                }

                $article->set_var( 'story_introtext', $introtext . '<br' . XHTML . '>'
                                   . $bodytext );
                $article->set_var( 'story_text_no_br', $introtext . $bodytext );
            }
            $article->set_var( 'story_introtext_only', $introtext );
            $article->set_var( 'story_bodytext_only', $bodytext );

            if(( $_CONF['trackback_enabled'] || $_CONF['pingback_enabled'] ) &&
                    SEC_hasRights( 'story.ping' )) {
                $url = $_CONF['site_admin_url']
                     . '/trackback.php?mode=sendall&amp;id=' . $story->getSid();
                $article->set_var( 'send_trackback_link',
                    COM_createLink($LANG_TRB['send_trackback'], $url)
                );
                $pingico = '<img src="' . $_CONF['layout_url'] . '/images/sendping.'
                    . $_IMAGE_TYPE . '" alt="' . $LANG_TRB['send_trackback']
                    . '" title="' . $LANG_TRB['send_trackback'] . '"' . XHTML . '>';
                $article->set_var( 'send_trackback_icon',
                    COM_createLink($pingico, $url)
                );
                $article->set_var( 'send_trackback_url', $url );
                $article->set_var( 'lang_send_trackback_text',
                                   $LANG_TRB['send_trackback'] );
            }
            $article->set_var( 'story_display',
                               ( $index == 'p' ) ? 'preview' : 'article' );
            $article->set_var( 'story_counter', 0 );
        } else {
            $article->set_var( 'story_introtext', $introtext );
            $article->set_var( 'story_text_no_br', $introtext );
            $article->set_var( 'story_introtext_only', $introtext );

            if( !empty( $bodytext )) {
                $article->set_var( 'lang_readmore', $LANG01[2] );
                $article->set_var( 'lang_readmore_words', $LANG01[62] );
                $numwords = COM_numberFormat (sizeof( explode( ' ', strip_tags( $bodytext ))));
                $article->set_var( 'readmore_words', $numwords );

                $article->set_var( 'readmore_link',
                    COM_createLink(
                        $LANG01[2],
                        $articleUrl,
                        array('class'=>'story-read-more-link')
                    )
                    . ' (' . $numwords . ' ' . $LANG01[62] . ') ' );
                $article->set_var('start_readmore_anchortag', '<a href="'
                        . $articleUrl . '" class="story-read-more-link">');
                $article->set_var('end_readmore_anchortag', '</a>');
                $article->set_var('read_more_class', 'class="story-read-more-link"');
            }

            if(( $story->DisplayElements('commentcode') >= 0 ) and ( $show_comments )) {
                $commentsUrl = COM_buildUrl( $_CONF['site_url']
                        . '/article.php?story=' . $story->getSid() ) . '#comments';
                $article->set_var( 'comments_url', $commentsUrl );
                $article->set_var( 'comments_text',
                        COM_numberFormat( $story->DisplayElements('comments') ) . ' ' . $LANG01[3] );
                $article->set_var( 'comments_count',
                        COM_numberFormat ( $story->DisplayElements('comments') ));
                $article->set_var( 'lang_comments', $LANG01[3] );
                $comments_with_count = sprintf( $LANG01[121], COM_numberFormat( $story->DisplayElements('comments') ));

                if ( $story->DisplayElements('comments') > 0 ) {
                    $result = DB_query( "SELECT UNIX_TIMESTAMP(date) AS day,username,fullname,{$_TABLES['comments']}.uid as cuid FROM {$_TABLES['comments']},{$_TABLES['users']} WHERE {$_TABLES['users']}.uid = {$_TABLES['comments']}.uid AND sid = '".DB_escapeString($story->getsid())."' ORDER BY date desc LIMIT 1" );
                    $C = DB_fetchArray( $result );

                    $recent_post_anchortag = '<span class="storybyline">'
                            . $LANG01[27] . ': '
                            . strftime( $_CONF['daytime'], $C['day'] ) . ' '
                            . $LANG01[104] . ' ' . COM_getDisplayName ($C['cuid'],
                                                    $C['username'], $C['fullname'])
                            . '</span>';
                    $article->set_var( 'comments_with_count', COM_createLink($comments_with_count, $commentsUrl));
                    $article->set_var( 'start_comments_anchortag', '<a href="'
                            . $commentsUrl . '">' );
                    $article->set_var( 'end_comments_anchortag', '</a>' );
                } else {
                    $article->set_var( 'comments_with_count', $comments_with_count);
                    $recent_post_anchortag = COM_createLink($LANG01[60],
                        $_CONF['site_url'] . '/comment.php?sid=' . $story->getsid()
                            . '&amp;pid=0&amp;type=article');
                }
                if( $story->DisplayElements( 'commentcode' ) == 0 &&
                 ($_CONF['commentsloginrequired'] == 0 || !COM_isAnonUser())) {
//                if( $story->DisplayElements( 'commentcode' ) == 0 ) {
                    $postCommentUrl = $_CONF['site_url'] . '/comment.php?sid='
                                . $story->getSid() . '&amp;pid=0&amp;type=article';
                    $article->set_var( 'post_comment_link',
                            COM_createLink($LANG01[60], $postCommentUrl,
                                           array('rel' => 'nofollow')));
                    $article->set_var( 'lang_post_comment', $LANG01[60] );
                    $article->set_var( 'start_post_comment_anchortag',
                                       '<a href="' . $postCommentUrl
                                       . '" rel="nofollow">' );
                    $article->set_var( 'end_post_comment_anchortag', '</a>' );
                }
            }

            if(( $_CONF['trackback_enabled'] || $_CONF['pingback_enabled'] ) &&
                    ( $story->DisplayElements('trackbackcode') >= 0 ) && ( $show_comments )) {
                $num_trackbacks = COM_numberFormat( $story->DisplayElements('trackbacks') );
                $trackbacksUrl = COM_buildUrl( $_CONF['site_url']
                        . '/article.php?story=' . $story->getSid() ) . '#trackback';
                $article->set_var( 'trackbacks_url', $trackbacksUrl );
                $article->set_var( 'trackbacks_text', $num_trackbacks . ' '
                                                      . $LANG_TRB['trackbacks'] );
                $article->set_var( 'trackbacks_count', $num_trackbacks );
                $article->set_var( 'lang_trackbacks', $LANG_TRB['trackbacks'] );
                $article->set_var( 'trackbacks_with_count',
                    COM_createLink(
                        sprintf( $LANG01[122], $num_trackbacks ),
                        $trackbacksUrl
                    )
                );

                if(SEC_hasRights( 'story.ping' )) {
                    $pingurl = $_CONF['site_admin_url']
                        . '/trackback.php?mode=sendall&amp;id=' . $story->getSid();
                    $pingico = '<img src="' . $_CONF['layout_url'] . '/images/sendping.'
                        . $_IMAGE_TYPE . '" alt="' . $LANG_TRB['send_trackback']
                        . '" title="' . $LANG_TRB['send_trackback'] . '"' . XHTML . '>';
                    $article->set_var( 'send_trackback_icon',
                        COM_createLink($pingico, $pingurl)
                    );
                }

                if( $story->DisplayElements('trackbacks') > 0 ) {
                    $article->set_var( 'trackbacks_with_count',
                        COM_createLink(
                            sprintf( $LANG01[122], $num_trackbacks ),
                            $trackbacksUrl
                        )
                    );
                } else {
                    $article->set_var( 'trackbacks_with_count',
                            sprintf( $LANG01[122], $num_trackbacks )
                    );
                }
            }

            if(( $_CONF['hideemailicon'] == 1 ) ||
               ( empty( $_USER['username'] ) &&
                    (( $_CONF['loginrequired'] == 1 ) ||
                     ( $_CONF['emailstoryloginrequired'] == 1 )))) {
                $article->set_var( 'email_icon', '' );
            } else {
                $emailUrl = $_CONF['site_url'] . '/profiles.php?sid=' . $story->getSid()
                          . '&amp;what=emailstory';
                $emailicon = '<img src="' . $_CONF['layout_url'] . '/images/mail.'
                    . $_IMAGE_TYPE . '" alt="' . $LANG01[64] . '" title="'
                    . $LANG11[2] . '"' . XHTML . '>';
                $article->set_var( 'email_icon',
                    COM_createLink($emailicon, $emailUrl)
                );
                $article->set_var( 'email_story_url', $emailUrl );
                $article->set_var( 'lang_email_story', $LANG11[2] );
                $article->set_var( 'lang_email_story_alt', $LANG01[64] );
            }
            $printUrl = COM_buildUrl( $_CONF['site_url'] . '/article.php?story='
                                      . $story->getSid() . '&amp;mode=print' );
            if( $_CONF['hideprintericon'] == 1 ) {
                $article->set_var( 'print_icon', '' );
            } else {
                $printicon = '<img src="' . $_CONF['layout_url']
                    . '/images/print.' . $_IMAGE_TYPE . '" alt="' . $LANG01[65]
                    . '" title="' . $LANG11[3] . '"' . XHTML . '>';
                $article->set_var( 'print_icon',
                    COM_createLink($printicon, $printUrl, array('rel' => 'nofollow'))
                );
                $article->set_var( 'print_story_url', $printUrl );
                $article->set_var( 'lang_print_story', $LANG11[3] );
                $article->set_var( 'lang_print_story_alt', $LANG01[65] );
            }
            $article->set_var( 'pdf_icon', '' );

            if ($_CONF['backend'] == 1) {
                $tid = $story->displayElements('tid');
                $result = DB_query("SELECT filename, title FROM {$_TABLES['syndication']} WHERE type = 'article' AND topic = '".DB_escapeString($tid)."' AND is_enabled = 1");
                $feeds = DB_numRows($result);
                for ($i = 0; $i < $feeds; $i++) {
                    list($filename, $title) = DB_fetchArray($result);
                    $feedUrl = SYND_getFeedUrl($filename);
                    $feedTitle = sprintf($LANG11[6],$title);
                }
                if ( $feeds > 0 ) {
                    $feedicon = '<img src="'. $_CONF['layout_url'] . '/images/rss_small.'
                             . $_IMAGE_TYPE . '" alt="'. $feedTitle
                             .'" title="'. $feedTitle .'"' . XHTML . '>';
                    $article->set_var( 'feed_icon',COM_createLink($feedicon, $feedUrl,array("type" =>"application/rss+xml")));
                } else {
                    $article->set_var( 'feed_icon', '' );
                }
            } else {
                $article->set_var( 'feed_icon', '' );
            }
            $article->set_var( 'story_display', 'index' );

            $storycounter++;
            $article->set_var( 'story_counter', $storycounter );
        }
        $article->set_var( 'article_url', $articleUrl );
        $article->set_var( 'recent_post_anchortag', $recent_post_anchortag );

        $access = $story->checkAccess();
        $storyAccess = min($access, SEC_hasTopicAccess($story->DisplayElements('tid')));

        if( $storyAccess == 3 AND SEC_hasrights( 'story.edit' ) AND ( $index != 'p' )) {
            $article->set_var( 'edit_link',
                COM_createLink($LANG01[4], $_CONF['site_admin_url']
                    . '/story.php?edit=x&amp;sid=' . $story->getSid())
                );
            $article->set_var( 'edit_url', $_CONF['site_admin_url']
                    . '/story.php?edit=x&amp;sid=' . $story->getSid() );
            $article->set_var( 'lang_edit_text',  $LANG01[4] );
            $editicon = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
            $editiconhtml = '<img src="' . $editicon . '" alt="' . $LANG01[4] . '" title="' . $LANG01[4] . '"' . XHTML . '>';
            $article->set_var( 'edit_icon',
                COM_createLink(
                    $editiconhtml,
                    $_CONF['site_admin_url'] . '/story.php?edit=x&amp;sid=' . $story->getSid()
                )
            );
            $article->set_var( 'edit_image', $editiconhtml);
        }
        PLG_templateSetVars($article_filevar,$article);

        if ( $_CONF['rating_enabled'] != 0 && $index != 'p') {
            if ( @in_array($story->getSid(),$ratedIds)) {
                $static = true;
                $voted = 1;
            } else {
                $static = 0;
                $voted = 0;
            }
            $uid = isset($_USER['uid']) ? $_USER['uid'] : 1;
            if ( $_CONF['rating_enabled'] == 2 && $uid != $story->DisplayElements('owner_id')) {
                $article->set_var('rating_bar',RATING_ratingBar( 'article',$story->getSid(), $story->DisplayElements('votes'),$story->DisplayElements('rating'), $voted,5,$static,'sm'),false,true );
            } else if ( !COM_isAnonUser() && $uid != $story->DisplayElements('owner_id')) {
                $article->set_var('rating_bar',RATING_ratingBar( 'article',$story->getSid(), $story->DisplayElements('votes'),$story->DisplayElements('rating'), $voted,5,$static,'sm'),false,true );
            } else {
                $article->set_var('rating_bar',RATING_ratingBar( 'article',$story->getSid(), $story->DisplayElements('votes'),$story->DisplayElements('rating'), 1,5,TRUE,'sm'),false,true );
            }
        } else {
            $article->set_var('rating_bar','',false,true );
        }

        if( $story->DisplayElements('featured') == 1 ) {
            $article->set_var( 'lang_todays_featured_article', $LANG05[4] );
            $article->parse( 'story_bodyhtml', 'featuredbodytext', true );
        } elseif( $story->DisplayElements('statuscode') == 10 AND $story->DisplayElements('expire') <= time() ) {
            $article->parse( 'story_bodyhtml', 'archivestorybodytext', true );
        } else {
            $article->parse( 'story_bodyhtml', 'bodytext', true );
        }
        if ($index != 'p') {
            $article->create_instance($instance_id,$article_filevar);
        }
    } else {
        PLG_templateSetVars($article_filevar,$article);

        if ( $_CONF['rating_enabled'] != 0 ) {
            if ( @in_array($story->getSid(),$ratedIds)) {
                $static = true;
                $voted = 1;
            } else {
                $static = 0;
                $voted = 0;
            }
            $uid = isset($_USER['uid']) ? $_USER['uid'] : 1;
            if ( $_CONF['rating_enabled'] == 2 && $uid != $story->DisplayElements('owner_id')) {
                $article->set_var('rating_bar',RATING_ratingBar( 'article',$story->getSid(), $story->DisplayElements('votes'),$story->DisplayElements('rating'), $voted,5,$static,'sm'),false,true );
            } else if ( !COM_isAnonUser() && $uid != $story->DisplayElements('owner_id')) {
                $article->set_var('rating_bar',RATING_ratingBar( 'article',$story->getSid(), $story->DisplayElements('votes'),$story->DisplayElements('rating'), $voted,5,$static,'sm'),false,true );
            } else {
                $article->set_var('rating_bar',RATING_ratingBar( 'article',$story->getSid(), $story->DisplayElements('votes'),$story->DisplayElements('rating'), $voted,5,TRUE,'sm'),false,true );
            }
        } else {
            $article->set_var('rating_bar','',false,true );
        }
    }

    $article->parse('finalstory',$article_filevar);

    return $article->finish( $article->get_var( 'finalstory' ));
}

/**
* Extract links from an HTML-formatted text.
*
* Collects all the links in a story and returns them in an array.
*
* @param    string  $fulltext   the text to search for links
* @param    int     $maxlength  max. length of text in a link (can be 0)
* @return   array   an array of strings of form <a href="...">link</a>
*
*/
function ARTICLE_extractLinks( $fulltext, $maxlength = 26 )
{
    $rel = array();

    /* Only match anchor tags that contain 'href="<something>"'
     */
    preg_match_all( "/<a[^>]*href=[\"']([^\"']*)[\"'][^>]*>(.*?)<\/a>/i", $fulltext, $matches );
    for ( $i=0; $i< count( $matches[0] ); $i++ )
    {
        $matches[2][$i] = strip_tags( $matches[2][$i] );
        if ( !MBYTE_strlen( trim( $matches[2][$i] ) ) ) {
            $matches[2][$i] = strip_tags( $matches[1][$i] );
        }

        // if link is too long, shorten it and add ... at the end
        if ( ( $maxlength > 0 ) && ( MBYTE_strlen( $matches[2][$i] ) > $maxlength ) ) {
            $matches[2][$i] = substr( $matches[2][$i], 0, $maxlength - 3 ) . '...';
        }

        $rel[] = '<a href="' . $matches[1][$i] . '">'
               . str_replace(array("\015", "\012"), '', $matches[2][$i])
               . '</a>';
    }

    return( $rel );
}

/**
* Create "What's Related" links for a story
*
* Creates an HTML-formatted list of links to be used for the What's Related
* block next to a story (in article view).
*
* @param        string      $related    contents of gl_stories 'related' field
* @param        int         $uid        user id of the author
* @param        int         $tid        topic id
* @return       string      HTML-formatted list of links
*/

function ARTICLE_whatsRelated( $related, $uid, $tid )
{
    global $_CONF, $_TABLES, $_USER, $LANG24;

    // get the links from the story text
    if (!empty ($related)) {
        $rel = explode ("\n", $related);
    } else {
        $rel = array ();
    }

    if( !COM_isAnonUser() || (( $_CONF['loginrequired'] == 0 ) &&
           ( $_CONF['searchloginrequired'] == 0 ))) {
        // add a link to "search by author"
        if( $_CONF['contributedbyline'] == 1 ) {
            $author = COM_getDisplayName( $uid );
            $rel[] = "<a href=\"{$_CONF['site_url']}/search.php?mode=search&amp;type=stories&amp;author=$uid\">{$LANG24[37]} $author</a>";
        }

        // add a link to "search by topic"
        $topic = DB_getItem( $_TABLES['topics'], 'topic', "tid = '".DB_escapeString($tid)."'" );
        $rel[] = '<a href="' . $_CONF['site_url']
               . '/search.php?mode=search&amp;type=stories&amp;topic=' . $tid
               . '">' . $LANG24[38] . ' ' . stripslashes( $topic ) . '</a>';
    }

    $related = '';
    if( sizeof( $rel ) > 0 ) {
        $related = COM_checkWords( COM_makeList( $rel, 'list-whats-related' ));
    }

    return( $related );
}

/**
* Delete one image from a story
*
* Deletes scaled and unscaled image, but does not update the database.
*
* @param    string  $image  file name of the image (without the path)
*
*/
function ARTICLE_deleteImage ($image)
{
    global $_CONF;

    if (empty ($image)) {
        return;
    }

    $filename = $_CONF['path_images'] . 'articles/' . $image;
    if (!@unlink ($filename)) {
        // log the problem but don't abort the script
        COM_errorLog ('Unable to remove the following image from the article: ' . $filename);
    }

    // remove unscaled image, if it exists
    $lFilename_large = substr_replace ($image, '_original.',
                                       strrpos ($image, '.'), 1);
    $lFilename_large_complete = $_CONF['path_images'] . 'articles/'
                              . $lFilename_large;
    if (file_exists ($lFilename_large_complete)) {
        if (!@unlink ($lFilename_large_complete)) {
            // again, log the problem but don't abort the script
            COM_errorLog ('Unable to remove the following image from the article: ' . $lFilename_large_complete);
        }
    }
}

/**
* Delete all images from a story
*
* Deletes all scaled and unscaled images from the file system and the database.
*
* @param    string  $sid    story id
*
*/
function ARTICLE_deleteImages ($sid)
{
    global $_TABLES;

    $result = DB_query ("SELECT ai_filename FROM {$_TABLES['article_images']} WHERE ai_sid = '".DB_escapeString($sid)."'");
    $nrows = DB_numRows ($result);
    for ($i = 0; $i < $nrows; $i++) {
        $A = DB_fetchArray ($result);
        ARTICLE_deleteImage ($A['ai_filename']);
    }
    DB_delete ($_TABLES['article_images'], 'ai_sid', DB_escapeString($sid));
}

/**
* Return information for a story
*
* This is the story equivalent of PLG_getItemInfo. See lib-plugins.php for
* details.
*
* @param    string  $sid        story ID or '*'
* @param    string  $what       comma-separated list of story properties
* @param    int     $uid        user ID or 0 = current user
* @return   mixed               string or array of strings with the information
*
*/
function ARTICLE_getItemInfo($sid, $what, $uid = 0, $options = array())
{
    global $_CONF, $_TABLES, $LANG09;

    $properties = explode(',', $what);

    $fields = array();
    foreach ($properties as $p) {
        switch ($p) {
            case 'date-created':
                $fields[] = 'UNIX_TIMESTAMP(date) AS unixdate';
                break;
            case 'description':
            case 'raw-description':
                $fields[] = 'introtext';
                $fields[] = 'bodytext';
                break;
            case 'excerpt':
                $fields[] = 'introtext';
                break;
            case 'feed':
                $fields[] = 'tid';
                break;
            case 'id':
                $fields[] = 'sid';
                break;
            case 'title':
                $fields[] = 'title';
                break;
            case 'url':
            case 'label':
                $fields[] = 'sid';
                break;
            case 'status' :
                $fields[] = 'draft_flag';
                break;
            default:
                break;
        }
    }

    $fields = array_unique($fields);

    if (count($fields) == 0) {
        $retval = array();

        return $retval;
    }

    if ($sid == '*') {
        $where = ' WHERE';
    } else {
        $where = " WHERE (sid = '" . DB_escapeString($sid) . "') AND";
    }
    $where .= ' (date <= NOW())';
    if ($uid > 0) {
        $permSql = COM_getPermSql('AND', $uid)
                 . COM_getTopicSql('AND', $uid);
    } else {
        $permSql = COM_getPermSql('AND') . COM_getTopicSql('AND');
    }
    $sql = "SELECT " . implode(',', $fields) . " FROM {$_TABLES['stories']}" . $where . $permSql;
    if ($sid != '*') {
        $sql .= ' LIMIT 1';
    }

    $result = DB_query($sql);
    $numRows = DB_numRows($result);

    $retval = array();
    for ($i = 0; $i < $numRows; $i++) {
        $A = DB_fetchArray($result);

        $props = array();
        foreach ($properties as $p) {
            switch ($p) {
                case 'date-created':
                    $props['date-created'] = $A['unixdate'];
                    break;
                case 'description':
                    $props['description'] = trim(PLG_replaceTags(stripslashes($A['introtext']) . ' ' . stripslashes($A['bodytext'])));
                    break;
                case 'raw-description':
                    $props['raw-description'] = trim(stripslashes($A['introtext']) . ' ' . stripslashes($A['bodytext']));
                    break;
                case 'excerpt':
                    $excerpt = stripslashes($A['introtext']);
                    $props['excerpt'] = trim(PLG_replaceTags($excerpt));
                    break;
                case 'feed':
                    $feedfile = DB_getItem($_TABLES['syndication'], 'filename',
                                           "topic = '::all'");
                    if (empty($feedfile)) {
                        $feedfile = DB_getItem($_TABLES['syndication'], 'filename',
                                               "topic = '::frontpage'");
                    }
                    if (empty($feedfile)) {
                        $feedfile = DB_getItem($_TABLES['syndication'], 'filename',
                                               "topic = '{$A['tid']}'");
                    }
                    if (empty($feedfile)) {
                        $props['feed'] = '';
                    } else {
                        $props['feed'] = SYND_getFeedUrl($feedfile);
                    }
                    break;
                case 'id':
                    $props['id'] = $A['sid'];
                    break;
                case 'title':
                    $props['title'] = stripslashes($A['title']);
                    break;
                case 'url':
                    if (empty($A['sid'])) {
                        $props['url'] = COM_buildUrl($_CONF['site_url'].'/article.php?story=' . $sid);
                    } else {
                        $props['url'] = COM_buildUrl($_CONF['site_url'].'/article.php?story=' . $A['sid']);
                    }
                    break;
                case 'label':
                    $props['label'] = $LANG09[65];
                    break;
                case 'status' :
                    if ( $A['draft_flag'] == 0 ) {
                        $props['status'] = 1;
                    } else {
                        $props['status'] = 0;
                    }
                    break;
                default:
                    $props[$p] = '';
                    break;
            }
        }

        $mapped = array();
        foreach ($props as $key => $value) {
            if ($sid == '*') {
                if ($value != '') {
                    $mapped[$key] = $value;
                }
            } else {
                $mapped[$key] = $value;
            }
        }

        if ($sid == '*') {
            $retval[] = $mapped;
        } else {
            $retval = $mapped;
            break;
        }
    }

    if (($sid != '*') && (count($retval) == 1)) {
        $retval = $retval[0];
    }

    return $retval;
}



/**
* Delete a story.
*
* This is used to delete a story from the list of stories.
*
* @param    string  $sid    ID of the story to delete
* @return   string          HTML, e.g. a meta redirect
*
*/
function ARTICLE_deleteStory($sid)
{
    $args = array (
                    'sid' => $sid
                  );

    $output = '';

    PLG_invokeService('story', 'delete', $args, $output, $svc_msg);
    CACHE_remove_instance('whatsnew');
    CACHE_remove_instance('story_'.$sid);
    return $output;
}

/**
* Checks and Updates the featured status of all articles.
*
* Checks to see if any articles that were published for the future have been
* published and, if so, will see if they are featured.  If they are featured,
* this will set old featured article (if there is one) to normal
*
*/

function ARTICLE_featuredCheck()
{
    global $_TABLES;

    // allow only 1 featured for frontpage
    $sql = "SELECT sid FROM {$_TABLES['stories']} WHERE featured = 1 AND draft_flag = 0 AND frontpage = 1 AND date <= NOW() ORDER BY date DESC LIMIT 2";
    $result = DB_query($sql);
    $numrows = DB_numRows($result);
    if ($numrows > 1) {
        $F = DB_fetchArray($result);
        // un-feature all other featured frontpage story
        $sql = "UPDATE {$_TABLES['stories']} SET featured = 0 WHERE featured = 1 AND draft_flag = 0 AND frontpage = 1 AND date <= NOW() AND sid <> '{$F['sid']}'";
        DB_query($sql);
    }
    // check all topics
    $sql = "SELECT tid FROM {$_TABLES['topics']}";
    $topicResult = DB_query($sql);
    $topicRows = DB_numRows($topicResult);
    for($i = 0; $i < $topicRows; $i++) {
        $T = DB_fetchArray($topicResult);
        $sql = "SELECT sid FROM {$_TABLES['stories']} WHERE featured = 1 AND draft_flag = 0 AND tid = '{$T['tid']}' AND date <= NOW() ORDER BY date DESC LIMIT 2";
        $storyResult = DB_query($sql);
        $storyRows   = DB_numRows($storyResult);
        if ($storyRows > 1) {
            // OK, we have two or more featured stories in a topic, fix that
            $S = DB_fetchArray($storyResult);
            $sql = "UPDATE {$_TABLES['stories']} SET featured = 0 WHERE featured = 1 AND draft_flag = 0 AND tid = '{$T['tid']}' AND date <= NOW() AND sid <> '{$S['sid']}'";
            DB_query($sql);
        }
    }
}

/*
 * START SERVICES SECTION
 * This section implements the various services offered by the story module
 */


/**
 * Submit a new or updated story. The story is updated if it exists, or a new one is created
 *
 * @param   array   args    Contains all the data provided by the client
 * @param   string  &output OUTPUT parameter containing the returned text
 * @return  int		    Response code as defined in lib-plugins.php
 */
function service_submit_article($args, &$output, &$svc_msg)
{
    global $_CONF, $_TABLES, $_USER, $LANG24, $MESSAGE, $_GROUPS;

    if (!SEC_hasRights('article.edit')) {
        $output .= COM_siteHeader('menu', $MESSAGE[30])
                . COM_showMessageText($MESSAGE[31], $MESSAGE[30])
                . COM_siteFooter();

        return PLG_RET_AUTH_FAILED;
    }

    $gl_edit = false;
    if (isset($args['gl_edit'])) {
        $gl_edit = $args['gl_edit'];
    }
    if ($gl_edit) {
        /* This is EDIT mode, so there should be an old sid */
        if (empty($args['old_sid'])) {
            if (!empty($args['id'])) {
                $args['old_sid'] = $args['id'];
            } else {
                return PLG_RET_ERROR;
            }

            if (empty($args['sid'])) {
                $args['sid'] = $args['old_sid'];
            }
        }
    } else {
        if (empty($args['sid']) && !empty($args['id'])) {
            $args['sid'] = $args['id'];
        }
    }

    /* Store the first CATEGORY as the Topic ID */
    if (!empty($args['category'][0])) {
        $args['tid'] = $args['category'][0];
    }

    $content = '';
    if (!empty($args['content'])) {
        $content = $args['content'];
    } else if (!empty($args['summary'])) {
        $content = $args['summary'];
    }
    if (!empty($content)) {
        $parts = explode('[page_break]', $content);
        if (count($parts) == 1) {
            $args['introtext'] = $content;
            $args['bodytext']  = '';
        } else {
            $args['introtext'] = array_shift($parts);
            $args['bodytext']  = implode('[page_break]', $parts);
        }
    }

    /* Apply filters to the parameters passed by the webservice */

    if ($args['gl_svc']) {
        if (isset($args['mode'])) {
            $args['mode'] = COM_applyBasicFilter($args['mode']);
        }
        if (isset($args['editopt'])) {
            $args['editopt'] = COM_applyBasicFilter($args['editopt']);
        }
    }

    /* - START: Set all the defaults - */

    if (empty($args['tid'])) {
        // see if we have a default topic
        $topic = DB_getItem($_TABLES['topics'], 'tid',
                            'is_default = 1' . COM_getPermSQL('AND'));
        if (!empty($topic)) {
            $args['tid'] = $topic;
        } else {
            // otherwise, just use the first one
            $o = array();
            $s = array();
            if (service_getTopicList_story(array('gl_svc' => true), $o, $s) == PLG_RET_OK) {
                $args['tid'] = $o[0];
            } else {
                $svc_msg['error_desc'] = 'No topics available';
                return PLG_RET_ERROR;
            }
        }
    }

    if(empty($args['owner_id'])) {
        $args['owner_id'] = $_USER['uid'];
    }

    if (empty($args['group_id'])) {
        $args['group_id'] = SEC_getFeatureGroup('story.edit', $_USER['uid']);
    }

    if (empty($args['postmode'])) {
        $args['postmode'] = $_CONF['postmode'];

        if (!empty($args['content_type'])) {
            if ($args['content_type'] == 'text') {
                $args['postmode'] = 'text';
            } else if (($args['content_type'] == 'html')
                    || ($args['content_type'] == 'xhtml')) {
                $args['postmode'] = 'html';
            }
        }
    }

    if ($args['gl_svc']) {

        /* Permissions */
        if (!isset($args['perm_owner'])) {
            $args['perm_owner'] = $_CONF['default_permissions_story'][0];
        } else {
            $args['perm_owner'] = COM_applyBasicFilter($args['perm_owner'], true);
        }
        if (!isset($args['perm_group'])) {
            $args['perm_group'] = $_CONF['default_permissions_story'][1];
        } else {
            $args['perm_group'] = COM_applyBasicFilter($args['perm_group'], true);
        }
        if (!isset($args['perm_members'])) {
            $args['perm_members'] = $_CONF['default_permissions_story'][2];
        } else {
            $args['perm_members'] = COM_applyBasicFilter($args['perm_members'], true);
        }
        if (!isset($args['perm_anon'])) {
            $args['perm_anon'] = $_CONF['default_permissions_story'][3];
        } else {
            $args['perm_anon'] = COM_applyBasicFilter($args['perm_anon'], true);
        }

        if (!isset($args['draft_flag'])) {
            $args['draft_flag'] = $_CONF['draft_flag'];
        }

        if (empty($args['frontpage'])) {
            $args['frontpage'] = $_CONF['frontpage'];
        }

        if (empty($args['show_topic_icon'])) {
            $args['show_topic_icon'] = $_CONF['show_topic_icon'];
        }
    }
    /* - END: Set all the defaults - */

    if (!isset($args['sid'])) {
        $args['sid'] = '';
    }
    $args['sid'] = COM_sanitizeID($args['sid']);
    if (!$gl_edit) {
        if (strlen($args['sid']) > ARTICLE_MAX_ID_LENGTH) {
            $args['sid'] = WS_makeId($args['slug'], ARTICLE_MAX_ID_LENGTH);
        }
    }

    $story = new Blog();

    /*$gl_edit = false;
    if (isset($args['gl_edit'])) {
        $gl_edit = $args['gl_edit'];
    }*/
    if ($gl_edit && !empty($args['gl_etag'])) {
        /* First load the original story to check if it has been modified */
        $result = $story->loadFromDatabase($args['sid']);
        if ($result == ARTICLE_LOADED_OK) {
            if ($args['gl_etag'] != date('c', $story->_date)) {
                $svc_msg['error_desc'] = 'A more recent version of the story is available';
                return PLG_RET_PRECONDITION_FAILED;
            }
        } else {
            $svc_msg['error_desc'] = 'Error loading story';
            return PLG_RET_ERROR;
        }
    }

    /* This function is also doing the security checks */
    $result = $story->loadFromArgsArray($args);

    $sid = $story->getSid();

    switch ($result) {
    case ARTICLE_DUPLICATE_SID:
        $output .= COM_siteHeader ('menu', $LANG24[5]);
        $output .= COM_errorLog ($LANG24[24], 2);
        if (!$args['gl_svc']) {
            if ( $args['type'] == 'submission' ) {
                $output .= ARTICLE_edit($sid, 'moderate');
            } else {
                $output .= ARTICLE_edit($sid);
            }
        }
        $output .= COM_siteFooter ();
        return PLG_RET_ERROR;
    case ARTICLE_EXISTING_NO_EDIT_PERMISSION:
        $output .= COM_siteHeader('menu', $MESSAGE[30])
                . COM_showMessageText($MESSAGE[31], $MESSAGE[30])
                . COM_siteFooter ();
        COM_accessLog("User {$_USER['username']} tried to illegally submit or edit story $sid.");
        return PLG_RET_PERMISSION_DENIED;
    case ARTICLE_NO_ACCESS_PARAMS:
        $output .= COM_siteHeader('menu', $MESSAGE[30])
                . COM_showMessageText($MESSAGE[31], $MESSAGE[30])
                . COM_siteFooter ();
        COM_accessLog("User {$_USER['username']} tried to illegally submit or edit story $sid.");
        return PLG_RET_PERMISSION_DENIED;
    case ARTICLE_EMPTY_REQUIRED_FIELDS:
        $output .= COM_siteHeader('menu');
        $output .= COM_errorLog($LANG24[31],2);
        if (!$args['gl_svc']) {
            $output .= ARTICLE_edit($sid);
        }
        $output .= COM_siteFooter();
        return PLG_RET_ERROR;
    default:
        break;
    }

    /* Image upload is not supported by the web-service at present */
    if (!$args['gl_svc']) {
        // Delete any images if needed
        if (array_key_exists('delete', $args)) {
            $delete = count($args['delete']);
            for ($i = 1; $i <= $delete; $i++) {
                $ai_filename = DB_getItem ($_TABLES['article_images'],'ai_filename', "ai_sid = '".DB_escapeString($sid)."' AND ai_img_num = " . intval(key($args['delete'])));
                ARTICLE_deleteImage ($ai_filename);

                DB_query ("DELETE FROM {$_TABLES['article_images']} WHERE ai_sid = '".DB_escapeString($sid)."' AND ai_img_num = '" . intval(key($args['delete'])) ."'");
                next($args['delete']);
            }
        }

        // OK, let's upload any pictures with the article
        if (DB_count($_TABLES['article_images'], 'ai_sid', DB_escapeString($sid)) > 0) {
            $index_start = DB_getItem($_TABLES['article_images'],'max(ai_img_num)',"ai_sid = '".DB_escapeString($sid)."'") + 1;
        } else {
            $index_start = 1;
        }

        if (count($_FILES) > 0 AND $_CONF['maximagesperarticle'] > 0) {
            require_once($_CONF['path_system'] . 'classes/upload.class.php');
            $upload = new upload();

            if (isset ($_CONF['debug_image_upload']) && $_CONF['debug_image_upload']) {
                $upload->setLogFile ($_CONF['path'] . 'logs/error.log');
                $upload->setDebug (true);
            }
            $upload->setMaxFileUploads ($_CONF['maximagesperarticle']);
            $upload->setAutomaticResize(true);
            if ($_CONF['keep_unscaled_image'] == 1) {
                $upload->keepOriginalImage (true);
            } else {
                $upload->keepOriginalImage (false);
            }
            $upload->setAllowedMimeTypes (array (
                    'image/gif'   => '.gif',
                    'image/jpeg'  => '.jpg,.jpeg',
                    'image/pjpeg' => '.jpg,.jpeg',
                    'image/x-png' => '.png',
                    'image/png'   => '.png'
                    ));
            $upload->setFieldName('file');
            if (!$upload->setPath($_CONF['path_images'] . 'articles')) {
                $output = COM_siteHeader ('menu', $LANG24[30]);
                $output .= COM_startBlock ($LANG24[30], '', COM_getBlockTemplate ('_msg_block', 'header'));
                $output .= $upload->printErrors (false);
                $output .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
                $output .= COM_siteFooter ();
                echo $output;
                exit;
            }

            // NOTE: if $_CONF['path_to_mogrify'] is set, the call below will
            // force any images bigger than the passed dimensions to be resized.
            // If mogrify is not set, any images larger than these dimensions
            // will get validation errors
            $upload->setMaxDimensions($_CONF['max_image_width'], $_CONF['max_image_height']);
            $upload->setMaxFileSize($_CONF['max_image_size']); // size in bytes, 1048576 = 1MB

            // Set file permissions on file after it gets uploaded (number is in octal)
            $upload->setPerms('0644');
            $filenames = array();

                    $sql = "SELECT MAX(ai_img_num) + 1 AS ai_img_num FROM " . $_TABLES['article_images'] . " WHERE ai_sid = '" . DB_escapeString($sid) ."'";
        	        $result = DB_query( $sql,1 );
        	        $row = DB_fetchArray( $result );
        	        $ai_img_num = $row['ai_img_num'];
        	        if ( $ai_img_num < 1 ) {
        	            $ai_img_num = 1;
        	        }

            for ($z = 0; $z < $_CONF['maximagesperarticle']; $z++ ) {
                $curfile['name'] = $_FILES['file']['name'][$z];
                if (!empty($curfile['name'])) {
                    $pos = strrpos($curfile['name'],'.') + 1;
                    $fextension = substr($curfile['name'], $pos);

                    $filenames[] = $sid . '_' . $ai_img_num . '.' . $fextension;
                    $ai_img_num++;
                } else {
                    $filenames[] = '';
                }
            }
            $upload->setFileNames($filenames);
            $upload->uploadFiles();

            if ($upload->areErrors()) {
                $retval = COM_siteHeader('menu', $LANG24[30]);
                $retval .= COM_startBlock ($LANG24[30], '',
                            COM_getBlockTemplate ('_msg_block', 'header'));
                $retval .= $upload->printErrors(false);
                $retval .= COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
                $retval .= COM_siteFooter();
                echo $retval;
                exit;
            }
            for ($z = 0; $z < $_CONF['maximagesperarticle']; $z++ ) {
                if ( $filenames[$z] != '' ) {
                    $sql = "SELECT MAX(ai_img_num) + 1 AS ai_img_num FROM " . $_TABLES['article_images'] . " WHERE ai_sid = '" . DB_escapeString($sid) ."'";
        	        $result = DB_query( $sql,1 );
        	        $row = DB_fetchArray( $result );
        	        $ai_img_num = $row['ai_img_num'];
        	        if ( $ai_img_num < 1 ) {
        	            $ai_img_num = 1;
        	        }
                    DB_query("INSERT INTO {$_TABLES['article_images']} (ai_sid, ai_img_num, ai_filename) VALUES ('".DB_escapeString($sid)."', $ai_img_num, '" . DB_escapeString($filenames[$z]) . "')");
                }
            }
        }

        if ($_CONF['maximagesperarticle'] > 0) {
            $errors = $story->insertImages();
            if (count($errors) > 0) {
                $output = COM_siteHeader ('menu', $LANG24[54]);
                $output .= COM_startBlock ($LANG24[54], '',
                                COM_getBlockTemplate ('_msg_block', 'header'));
                $output .= $LANG24[55] . '<p>';
                for ($i = 1; $i <= count($errors); $i++) {
                    $output .= current($errors) . '<br' . XHTML . '>';
                    next($errors);
                }
                $output .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
                $output .= ARTICLE_edit($sid);
                $output .= COM_siteFooter();
                echo $output;
                exit;
            }
        }
    }

    $result = $story->saveToDatabase();

    if ($result == ARTICLE_SAVED) {
        // see if any plugins want to act on that story
        if ((! empty($args['old_sid'])) && ($args['old_sid'] != $sid)) {
            PLG_itemSaved($sid, 'article', $args['old_sid']);
        } else {
            PLG_itemSaved($sid, 'article');
        }

        // update feed(s) and Older Stories block
        COM_rdfUpToDateCheck ('article', $story->DisplayElements('tid'), $sid);
        COM_olderStuff ();

        if ($story->type == 'submission') {
            $output = COM_refresh ($_CONF['site_admin_url'] . '/moderation.php?msg=9');
        } else {
            $output = PLG_afterSaveSwitch($_CONF['aftersave_story'],
                    COM_buildURL("{$_CONF['site_url']}/article.php?story=$sid"),
                        'story', 9);
        }

        /* @TODO Set the object id here */
        $svc_msg['id'] = $sid;

        return PLG_RET_OK;
    }
}

/**
 * Delete an existing story
 *
 * @param   array   args    Contains all the data provided by the client
 * @param   string  &output OUTPUT parameter containing the returned text
 * @return  int		    Response code as defined in lib-plugins.php
 */
function service_delete_story($args, &$output, &$svc_msg)
{
    global $_CONF, $_TABLES, $_USER;

    if (empty($args['sid']) && !empty($args['id'])) {
        $args['sid'] = $args['id'];
    }

    if ($args['gl_svc']) {
        $args['sid'] = COM_applyBasicFilter($args['sid']);
    }

    $sid = $args['sid'];

    $result = DB_query ("SELECT tid,owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon FROM {$_TABLES['stories']} WHERE sid = '".DB_escapeString($sid)."'");
    $A = DB_fetchArray ($result);
    $access = SEC_hasAccess ($A['owner_id'], $A['group_id'], $A['perm_owner'],
                             $A['perm_group'], $A['perm_members'], $A['perm_anon']);
    $access = min ($access, SEC_hasTopicAccess ($A['tid']));
    if ($access < 3) {
        COM_accessLog ("User {$_USER['username']} tried to illegally delete story $sid.");
        $output = COM_refresh ($_CONF['site_admin_url'] . '/story.php');
        if ($_USER['uid'] > 1) {
            return PLG_RET_PERMISSION_DENIED;
        } else {
            return PLG_RET_AUTH_FAILED;
        }
    }

    ARTICLE_deleteImages ($sid);
    DB_query("DELETE FROM {$_TABLES['comments']} WHERE sid = '".DB_escapeString($sid)."' AND type = 'article'");
    DB_delete ($_TABLES['stories'], 'sid', DB_escapeString($sid));

    // delete Trackbacks
    DB_query ("DELETE FROM {$_TABLES['trackback']} WHERE sid = '".DB_escapeString($sid)."' AND type = 'article';");

    PLG_itemDeleted($sid, 'article');

    // update RSS feed and Older Stories block
    COM_rdfUpToDateCheck ();
    COM_olderStuff ();

    $output = COM_refresh ($_CONF['site_admin_url'] . '/story.php?msg=10');

    return PLG_RET_OK;
}

/**
 * Get an existing story
 *
 * @param   array   args    Contains all the data provided by the client
 * @param   string  &output OUTPUT parameter containing the returned text
 * @return  int		    Response code as defined in lib-plugins.php
 */
function service_get_story($args, &$output, &$svc_msg)
{
    global $_CONF, $_TABLES, $_USER;

    $output = array();
    $retval = '';

    if (!isset($_CONF['atom_max_stories'])) {
        $_CONF['atom_max_stories'] = 10; // set a resonable default
    }

    $svc_msg['output_fields'] = array(
                                    'draft_flag',
                                    'hits',
                                    'numemails',
                                    'comments',
                                    'trackbacks',
                                    'featured',
                                    'commentcode',
                                    'statuscode',
                                    'expire_date',
                                    'postmode',
                                    'advanced_editor_mode',
                                    'frontpage',
                                    'owner_id',
                                    'group_id',
                                    'perm_owner',
                                    'perm_group',
                                    'perm_members',
                                    'perm_anon'
                                     );

    if (empty($args['sid']) && !empty($args['id'])) {
        $args['sid'] = $args['id'];
    }

    if ($args['gl_svc']) {
        if (isset($args['mode'])) {
            $args['mode'] = COM_applyBasicFilter($args['mode']);
        }
        if (isset($args['sid'])) {
            $args['sid'] = COM_applyBasicFilter($args['sid']);
        }

        if (empty($args['sid'])) {
            $svc_msg['gl_feed'] = true;
        } else {
            $svc_msg['gl_feed'] = false;
        }
    } else {
        $svc_msg['gl_feed'] = false;
    }

    if (empty($args['mode'])) {
        $args['mode'] = 'view';
    }

    if (!$svc_msg['gl_feed']) {
        $sid = $args['sid'];
        $mode = $args['mode'];

        $story = new Story();

        $retval = $story->loadFromDatabase($sid, $mode);

        if ($retval != ARTICLE_LOADED_OK) {
            $output = $retval;
            return PLG_RET_ERROR;
        }

        reset($story->_dbFields);

        while (list($fieldname,$save) = each($story->_dbFields)) {
            $varname = '_' . $fieldname;
            $output[$fieldname] = $story->{$varname};
        }
        $output['username'] = $story->_username;
        $output['fullname'] = $story->_fullname;

        if ($args['gl_svc']) {
            if (($output['statuscode'] == ARTICLE_ARCHIVE_ON_EXPIRE) ||
                ($output['statuscode'] == ARTICLE_DELETE_ON_EXPIRE)) {
                // This date format is PHP 5 only,
                // but only the web-service uses the value
                $output['expire_date']  = date('c', $output['expire']);
            }
            $output['id']           = $output['sid'];
            $output['category']     = array($output['tid']);
            $output['published']    = date('c', $output['date']);
            $output['updated']      = date('c', $output['date']);
            if (empty($output['bodytext'])) {
                $output['content']  = $output['introtext'];
            } else {
                $output['content']  = $output['introtext'] . LB
                                    . '[page_break]' . LB . $output['bodytext'];
            }
            $output['content_type'] = ($output['postmode'] == 'html')
                                    ? 'html' : 'text';

            $owner_data = SESS_getUserDataFromId($output['owner_id']);

            $output['author_name']  = $owner_data['username'];

            $output['link_edit'] = $sid;
        }
    } else {
        $output = array();

        $mode = $args['mode'];

        if (isset($args['offset'])) {
            $offset = COM_applyBasicFilter($args['offset'], true);
        } else {
            $offset = 0;
        }
        $max_items = $_CONF['atom_max_stories'] + 1;

        $limit = " LIMIT $offset, $max_items";
        $order = " ORDER BY unixdate DESC";

        $sql
        = "SELECT STRAIGHT_JOIN s.*, UNIX_TIMESTAMP(s.date) AS unixdate, UNIX_TIMESTAMP(s.expire) as expireunix, "
            . "u.username, u.fullname, u.photo, u.email, t.topic, t.imageurl " . "FROM {$_TABLES['stories']} AS s, {$_TABLES['users']} AS u, {$_TABLES['topics']} AS t " . "WHERE (s.uid = u.uid) AND (s.tid = t.tid)" . COM_getPermSQL('AND', $_USER['uid'], 2, 's') . $order . $limit;
        $result = DB_query($sql);

        $count = 0;

        while (($story_array = DB_fetchArray($result, false)) !== false) {

            $count += 1;
            if ($count == $max_items) {
                $svc_msg['offset'] = $offset + $_CONF['atom_max_stories'];
                break;
            }

            $story = new Story();

            $story->loadFromArray($story_array);

            // This access check is not strictly necessary
            $access = SEC_hasAccess($story_array['owner_id'], $story_array['group_id'], $story_array['perm_owner'], $story_array['perm_group'],
                                $story_array['perm_members'], $story_array['perm_anon']);
            $story->_access = min($access, SEC_hasTopicAccess($story->_tid));

            if ($story->_access == 0) {
                continue;
            }

            $story->_sanitizeData();

            reset($story->_dbFields);

            $output_item = array ();

            while (list($fieldname,$save) = each($story->_dbFields)) {
                $varname = '_' . $fieldname;
                $output_item[$fieldname] = $story->{$varname};
            }

            if ($args['gl_svc']) {
                if (($output_item['statuscode'] == ARTICLE_ARCHIVE_ON_EXPIRE) ||
                    ($output_item['statuscode'] == ARTICLE_DELETE_ON_EXPIRE)) {
                    // This date format is PHP 5 only,
                    // but only the web-service uses the value
                    $output_item['expire_date']  = date('c', $output_item['expire']);
                }
                $output_item['id']           = $output_item['sid'];
                $output_item['category']     = array($output_item['tid']);
                $output_item['published']    = date('c', $output_item['date']);
                $output_item['updated']      = date('c', $output_item['date']);
                if (empty($output_item['bodytext'])) {
                    $output_item['content']  = $output_item['introtext'];
                } else {
                    $output_item['content']  = $output_item['introtext'] . LB
                            . '[page_break]' . LB . $output_item['bodytext'];
                }
                $output_item['content_type'] = ($output_item['postmode'] == 'html') ? 'html' : 'text';

                $owner_data = SESS_getUserDataFromId($output_item['owner_id']);

                $output_item['author_name']  = $owner_data['username'];
            }
            $output[] = $output_item;
        }
    }

    return PLG_RET_OK;
}

/**
 * Get all the topics available
 *
 * @param   array   args    Contains all the data provided by the client
 * @param   string  &output OUTPUT parameter containing the returned text
 * @return  int         Response code as defined in lib-plugins.php
 */
function service_getTopicList_story($args, &$output, &$svc_msg)
{
    $output = COM_topicArray('tid');

    return PLG_RET_OK;
}

/*
 * END SERVICES SECTION
 */


function ARTICLE_edit($sid = '', $action = '', $errormsg = '', $currenttopic = '')
{
    global $_CONF, $_GROUPS, $_TABLES, $_USER, $LANG24, $LANG_ACCESS,
           $LANG_ADMIN, $MESSAGE, $LANG_ARTICLE;

    $display = '';
    switch ($action) {
    case 'clone' :
    case 'edit':
    case 'preview':
        $title = $LANG_ARTICLE['article_editor'];
        $saveoption = $LANG_ADMIN['save'];
        $submission = false;
        break;
    case 'moderate':
        $title = $LANGARTICLE['article_moderate'];
        $saveoption = $LANG_ADMIN['moderate'];
        $submission = true;
        break;
    case 'draft':
        $title = $LANG24[91];
        $saveoption = $LANG_ADMIN['save'];
        $submission = true;
        $action = 'edit';
        break;
    default :
        $title = $LANG24[5];
        $saveoption = $LANG_ADMIN['save'];
        $submission = false;
        $action = 'edit';
        break;
    }

    if (!isset ($_CONF['hour_mode'])) {
        $_CONF['hour_mode'] = 12;
    }

    if (!empty ($errormsg)) {
        $display .= COM_startBlock($LANG24[25], '',
                            COM_getBlockTemplate ('_msg_block', 'header'));
        $display .= $errormsg;
        $display .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
    }

    if (!empty ($currenttopic)) {
        $allowed = DB_getItem ($_TABLES['topics'], 'tid',
                        "tid = '" . DB_escapeString ($currenttopic) . "'" .
                        COM_getTopicSql ('AND'));

        if ($allowed != $currenttopic) {
            $currenttopic = '';
        }
    }

    $story = new Blog();

    if ($action == 'preview') {
        // Handle Magic GPC Garbage:
        while (list($key, $value) = each($_POST)) {
            if (!is_array($value)) {
                $_POST[$key] = COM_stripslashes($value);
            } else {
                while (list($subkey, $subvalue) = each($value)) {
                    $value[$subkey] = COM_stripslashes($subvalue);
                }
            }
        }
        $result = $story->loadFromArgsArray($_POST);
    } else {
        $result = $story->loadFromDatabase($sid, $action);
    }
    if ( ($result == ARTICLE_PERMISSION_DENIED) || 
            ($result == ARTICLE_NO_ACCESS_PARAMS) ) {
        $display .= COM_startBlock($LANG_ACCESS['accessdenied'], '',
                        COM_getBlockTemplate('_msg_block', 'header'));
        $display .= $LANG24[42];
        $display .= COM_endBlock(COM_getBlockTemplate('_msg_block', 'footer'));
        COM_accessLog("User {$_USER['username']} tried to illegally access story $sid. - ARTICLE_PERMISSION_DENIED or ARTICLE_NO_ACCESS_PARAMS - ".$result);
        return $display;

    } elseif ( ($result == ARTICLE_EDIT_DENIED) || 
            ($result == ARTICLE_EXISTING_NO_EDIT_PERMISSION) ) {

        $display .= COM_startBlock($LANG_ACCESS['accessdenied'], '',
                                COM_getBlockTemplate('_msg_block', 'header'));
        $display .= $LANG24[41];
        $display .= COM_endBlock (COM_getBlockTemplate('_msg_block', 'footer'));
        $display .= ARTICLE_renderArticle($story, 'p');
        COM_accessLog("User {$_USER['username']} tried to illegally edit story $sid. - ARTICLE_EDIT_DENIED or ARTICLE_EXISTING_NO_EDIT_PERMISSION");
        return $display;

    } elseif ($result == ARTICLE_INVALID_SID) {

        if ($action == 'moderate') {
            // that submission doesn't seem to be there any more (may have been
            // handled by another Admin) - take us back to the moderation page
            return COM_refresh($_CONF['site_admin_url'] . '/moderation.php');
        } else {
            return COM_refresh($_CONF['site_admin_url'] . '/story.php');
        }
    } elseif ($result == ARTICLE_DUPLICATE_SID) {
        $display .= COM_errorLog ($LANG24[24], 2);
    }

    if (empty($currenttopic) && $story->EditElements('tid') == '') {
        $story->setTid( DB_getItem($_TABLES['topics'], 'tid',
                                'is_default = 1' . COM_getPermSQL('AND')));
    } else if ($story->EditElements('tid') == '') {
        $story->setTid($currenttopic);
    }

    if (SEC_hasRights('story.edit')) {
        $allowedTopicList = COM_topicList('tid,topic', 
                $story->EditElements('tid'), 1, true,0);
    } else {
        $allowedTopicList = COM_topicList('tid,topic', 
                $story->EditElements('tid'), 1, true,3);
    }

    if ($allowedTopicList == '') {
        $display .= COM_startBlock($LANG_ACCESS['accessdenied'], '',
                                COM_getBlockTemplate ('_msg_block', 'header'));
        $display .= $LANG24[42];
        $display .= COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
        COM_accessLog("User {$_USER['username']} tried to illegally access story $sid. No allowed topics.");
        return $display;
    }

    $T = new Template(ARTICLE_PI_PATH . 'templates/admin');

    if (isset($_CONF['advanced_editor']) && $_CONF['advanced_editor'] == 1) {
        // Set up the advanced editor
        $advanced_editormode = true;
        $T->set_file(array('editor'=>'editor_advanced.thtml'));
        if (file_exists($_CONF['path_layout'] . '/fckstyles.xml')) {
            $T->set_var('glfusionStyleBasePath',$_CONF['layout_url']);
        } else {
            $T->set_var('glfusionStyleBasePath',$_CONF['site_url'] . '/fckeditor');
        }

        USES_class_navbar();

        $T->set_var(array(
            'change_editormode' => 'onchange="change_editmode(this);"',
            'show_preview'      => 'none',
            'lang_expandhelp'   => $LANG24[67],
            'lang_reducehelp'   => $LANG24[68],
            'lang_publishdate'  => $LANG24[69],
            'lang_toolbar'      => $LANG24[70],
            'toolbar1'          => $LANG24[71],
            'toolbar2'          => $LANG24[72],
            'toolbar3'          => $LANG24[73],
            'toolbar4'          => $LANG24[74],
            'toolbar5'          => $LANG24[75],
        ) );

        if ($story->EditElements('advanced_editor_mode') == 1 || 
                $story->EditElements('postmode') == 'adveditor') {
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
        $T->set_var('xhtml', XHTML);
        $advanced_editormode = false;
    }

    $T->set_var ('hour_mode',      $_CONF['hour_mode']);

    if ($story->hasContent()) {
        $previewContent = ARTICLE_renderArticle($story, 'p');
        if ($advanced_editormode AND $previewContent != '' ) {
            $T->set_var('preview_content', $previewContent);
        } elseif ($previewContent != '') {
            $display = COM_startBlock ($LANG24[26], '',
                            COM_getBlockTemplate ('_admin_block', 'header'));
            $display .= $previewContent;
            $display .= COM_endBlock (COM_getBlockTemplate ('_admin_block', 'footer'));
        }
    }

    if ($advanced_editormode) {
        $navbar = new navbar;

        if (!empty ($previewContent)) {
            $navbar->add_menuitem($LANG24[79],
                    'showhideEditorDiv("preview",0);return false;',true);
            $navbar->add_menuitem($LANG24[80],
                    'showhideEditorDiv("editor",1);return false;',true);
            $navbar->add_menuitem($LANG24[81],
                    'showhideEditorDiv("publish",2);return false;',true);
            $navbar->add_menuitem($LANG24[82],
                    'showhideEditorDiv("images",3);return false;',true);
            $navbar->add_menuitem($LANG24[83],
                    'showhideEditorDiv("archive",4);return false;',true);
            $navbar->add_menuitem($LANG24[84],
                    'showhideEditorDiv("perms",5);return false;',true);
            $navbar->add_menuitem($LANG24[85],
                    'showhideEditorDiv("all",6);return false;',true);
        }  else {
            $navbar->add_menuitem($LANG24[80],
                    'showhideEditorDiv("editor",0);return false;',true);
            $navbar->add_menuitem($LANG24[81],
                    'showhideEditorDiv("publish",1);return false;',true);
            $navbar->add_menuitem($LANG24[82],
                    'showhideEditorDiv("images",2);return false;',true);
            $navbar->add_menuitem($LANG24[83],
                    'showhideEditorDiv("archive",3);return false;',true);
            $navbar->add_menuitem($LANG24[84],
                    'showhideEditorDiv("perms",4);return false;',true);
            $navbar->add_menuitem($LANG24[85],
                    'showhideEditorDiv("all",5);return false;',true);
        }

        if ($action == 'preview') {
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
    $display .= COM_startBlock ($title, '',
                        COM_getBlockTemplate ('_admin_block', 'header'));
    $oldsid = $story->EditElements('originalSid');
    if (!empty($oldsid)) {
        $delbutton = '<input type="submit" value="' . $LANG_ADMIN['delete']
                   . '" name="deletestory"%s' . XHTML . '>';
        $jsconfirm = ' onclick="return confirm(\'' . $MESSAGE[76] . '\');"';
        $T->set_var('delete_option',
                                   sprintf ($delbutton, $jsconfirm));
        $T->set_var('delete_option_no_confirmation',
                                   sprintf ($delbutton, ''));
    }
    if ($submission || ($story->type == 'submission')) {
        $T->set_var('submission_option',
                '<input type="hidden" name="type" value="submission"' . XHTML . '>');
    }
    //$T->set_var('lang_author', $LANG24[7]);
    $storyauthor = COM_getDisplayName($story->EditElements('uid'));
    $storyauthor_select= COM_optionList($_TABLES['users'], 'uid,username',
            $story->EditElements('uid'));
    $ownername = COM_getDisplayName($story->EditElements('owner_id'));

    if (SEC_hasRights('story.edit')) {
        $owner_dropdown = COM_buildOwnerList('owner_id',
                $story->EditElements('owner_id'));
        $group_dropdown = SEC_getGroupDropdown($story->EditElements('group_id'), 3);
    } else {
        $owner_dropdown = '<input type="hidden" name="owner_id" value="'.
            $story->editElements('owner_id').'" />'.$ownername;
        $group_dropdown = '<input type="hidden" name="group_id" value="' .
                $story->EditElements('group_id') . '"/>';
        $group_dropdown .= DB_getItem($_TABLES['groups'],'grp_name',
                'grp_id='.$story->EditElements('group_id'));
    }

    if (SEC_inGroup($story->EditElements('group_id'))) {
        $gdrpdown = SEC_getGroupDropdown($story->EditElements('group_id'), 3);
    } else {
        $gdrpdown = '<input type="hidden" name="group_id" value="' .
                $story->EditElements('group_id').'"/>' .
                 DB_getItem($_TABLES['groups'], 'grp_name', 
                        'grp_id='.$story->EditElements('group_id'));
    }

    $curtime = COM_getUserDateTimeFormat($story->EditElements('date'));
    $publish_ampm = '';
    $publish_hour = $story->EditElements('publish_hour');
    if ($publish_hour >= 12) {
        if ($publish_hour > 12) {
            $publish_hour = $publish_hour - 12;
        }
        $ampm = 'pm';
    } else {
        $ampm = 'am';
    }
    $ampm_select = COM_getAmPmFormSelection ('publish_ampm', $ampm);
    $month_options = COM_getMonthFormOptions($story->EditElements('publish_month'));
    $day_options = COM_getDayFormOptions($story->EditElements('publish_day'));
    $year_options = COM_getYearFormOptions($story->EditElements('publish_year'));
    if ($_CONF['hour_mode'] == 24) {
        $hour_options = COM_getHourFormOptions($story->EditElements('publish_hour'), 24);
    } else {
        $hour_options = COM_getHourFormOptions($publish_hour);
    }
    $minute_options = COM_getMinuteFormOptions($story->EditElements('publish_minute'));

    $T->set_var(array(
        'article_author'      => $storyauthor,
        'article_author_select' => $storyauthor_select,
        'author'            => $storyauthor,
        'story_uid'         => $story->EditElements('uid'),

        // user access info
        //'lang_accessrights' => $LANG_ACCESS['accessrights'],
        //'lang_owner'        => $LANG_ACCESS['owner'],
        'owner_username'    => DB_getItem($_TABLES['users'],
                                  'username', 'uid = ' .
                                  $story->EditElements( 'owner_id' )),
        'owner_name'        => $ownername,
        'owner'             => $ownername,
        'owner_id'          => $story->EditElements('owner_id'),
        //'owner_dropdown'    => $ownerInfo,
        'owner_dropdown'    => $owner_dropdown,
        //'lang_group'        => $LANG_ACCESS['group'],

        'group_dropdown'    => $group_dropdown,
        //'lang_permissions'  => $LANG_ACCESS['permissions'],
        //'lang_perm_key'     => $LANG_ACCESS['permissionskey'],
        'permissions_editor' => SEC_getPermissionsHTML(
                    $story->EditElements('perm_owner'),
                    $story->EditElements('perm_group'),
                    $story->EditElements('perm_members'),
                    $story->EditElements('perm_anon') ),
        'permissions_msg'   => $LANG_ACCESS['permmsg'],
        //'lang_date'         => $LANG24[15],
        'publish_second'    => $story->EditElements('publish_second'),
        'publishampm_selection' => $ampm_select,
        'publish_month_options' => $month_options,
        'publish_day_options'   => $day_options,
        'publish_year_options'  => $year_options,
        'publish_hour_options'  => $hour_options,
        'publish_minute_options' => $minute_options,
        'publish_date_explanation' => $LANG24[46],  // set here to allow heml
        'story_unixstamp'       => $story->EditElements('unixdate'),
        'expire_second'         => $story->EditElements('expire_second'),
        'pi_url'            => ARTICLE_URL,
    ) );

    $expire_ampm = '';
    $expire_hour = $story->EditElements('expire_hour');
    if ($expire_hour >= 12) {
        if ($expire_hour > 12) {
            $expire_hour = $expire_hour - 12;
        }
        $ampm = 'pm';
    } else {
        $ampm = 'am';
    }
    $ampm_select = COM_getAmPmFormSelection ('expire_ampm', $ampm);
    if (empty ($ampm_select)) {
        // have a hidden field to 24 hour mode to prevent JavaScript errors
        $ampm_select = '<input type="hidden" name="expire_ampm" value=""' . XHTML . '>';
    }
    $month_options = COM_getMonthFormOptions($story->EditElements('expire_month'));
    $day_options = COM_getDayFormOptions($story->EditElements('expire_day'));
    $year_options = COM_getYearFormOptions($story->EditElements('expire_year'));
    if ($_CONF['hour_mode'] == 24) {
        $hour_options = COM_getHourFormOptions ($story->EditElements('expire_hour'), 24);
    } else {
        $hour_options = COM_getHourFormOptions ($expire_hour);
    }
    $minute_options = COM_getMinuteFormOptions($story->EditElements('expire_minute'));

    $T->set_var(array(
        'expireampm_selection'  => $ampm_select,
        'expire_month_options'  => $month_options,
        'expire_day_options'    => $day_options,

        'expire_year_options'   => $year_options,
        'expire_hour_options'   => $hour_options,
        'expire_minute_options' => $minute_options,
        //'expire_date_explanation' => $LANG24[46],
        'story_unixstamp'       => $story->EditElements('expirestamp'),
        //'lang_archivetitle'     => $LANG24[58],
        //'lang_option'           => $LANG24[59],
        //'lang_enabled'          => $LANG_ADMIN['enabled'],
        //'lang_story_stats'      => $LANG24[87],
        //'lang_optionarchive'    => $LANG24[61],
        //'lang_optiondelete'     => $LANG24[62],
        //'lang_title'            => $LANG_ADMIN['title'],
        'story_title'           => $story->EditElements('title'),
        //'lang_topic'            => $LANG_ADMIN['topic'],
        'topic_options'         => $allowedTopicList,
        //'lang_show_topic_icon'  => $LANG24[56],
    ) );

    if ($story->EditElements('statuscode') == ARTICLE_ARCHIVE_ON_EXPIRE) {
        $T->set_var('is_checked2', 'checked="checked"');
        $T->set_var('is_checked3', 'checked="checked"');
        $T->set_var('showarchivedisabled', 'false');
    } elseif ($story->EditElements('statuscode') == ARTICLE_DELETE_ON_EXPIRE) {
        $T->set_var('is_checked2', 'checked="checked"');
        $T->set_var('is_checked4', 'checked="checked"');
        $T->set_var('showarchivedisabled', 'false');
    } else {
        $T->set_var('showarchivedisabled', 'true');
    }

    if ($story->EditElements('show_topic_icon') == 1) {
        $T->set_var('show_topic_icon_checked', 'checked="checked"');
    } else {
        $T->set_var('show_topic_icon_checked', '');
    }
    //$T->set_var('lang_draft', $LANG24[34]);
    if ($story->EditElements('draft_flag')) {
        $T->set_var('is_checked', 'checked="checked"');
    }
    //$T->set_var ('lang_mode', $LANG24[3]);
    $T->set_var('status_options',
            COM_optionList($_TABLES['statuscodes'], 'code,name',
                            $story->EditElements('statuscode')));
    $T->set_var('comment_options',
            COM_optionList($_TABLES['commentcodes'], 'code,name',
                            $story->EditElements('commentcode')));
    $T->set_var('trackback_options',
            COM_optionList($_TABLES['trackbackcodes'], 'code,name',
                            $story->EditElements('trackbackcode')));
    // comment expire
    //$T->set_var ('lang_cmt_disable', $LANG24[63]);
    if ($story->EditElements('cmt_close') ) {
        $T->set_var('is_checked5', 'checked="checked"'); //check box if enabled
        $T->set_var('showcmtclosedisabled', 'false');
    } else {
        $T->set_var('showcmtclosedisabled', 'true');
    }

    $month_options = COM_getMonthFormOptions($story->EditElements('cmt_close_month'));
    $T->set_var('cmt_close_month_options', $month_options);

    $day_options = COM_getDayFormOptions($story->EditElements('cmt_close_day'));
    $T->set_var('cmt_close_day_options', $day_options);

    $year_options = COM_getYearFormOptions($story->EditElements('cmt_close_year'));
    $T->set_var('cmt_close_year_options', $year_options);

    $cmt_close_ampm = '';
    $cmt_close_hour = $story->EditElements('cmt_close_hour');
    //correct hour
    if ($cmt_close_hour >= 12) {
        if ($cmt_close_hour > 12) {
            $cmt_close_hour = $cmt_close_hour - 12;
        }
        $ampm = 'pm';
    } else {
        $ampm = 'am';
    }
    $ampm_select = COM_getAmPmFormSelection ('cmt_close_ampm', $ampm);
    if (empty ($ampm_select)) {
        // have a hidden field to 24 hour mode to prevent JavaScript errors
        $ampm_select = '<input type="hidden" name="cmt_close_ampm" value=""' . XHTML . '>';
    }
    $T->set_var ('cmt_close_ampm_selection', $ampm_select);

    if ($_CONF['hour_mode'] == 24) {
        $hour_options = COM_getHourFormOptions ($story->EditElements('cmt_close_hour'), 24);
    } else {
        $hour_options = COM_getHourFormOptions ($cmt_close_hour);
    }
    $T->set_var('cmt_close_hour_options', $hour_options);

    $minute_options = COM_getMinuteFormOptions($story->EditElements('cmt_close_minute'));
    $T->set_var('cmt_close_minute_options', $minute_options);

    $T->set_var('cmt_close_second', $story->EditElements('cmt_close_second'));

    if (($_CONF['onlyrootfeatures'] == 1 && SEC_inGroup('Root'))
        or ($_CONF['onlyrootfeatures'] !== 1)) {
        $featured_options = "<select name=\"featured\">" . LB
                          . COM_optionList ($_TABLES['featurecodes'], 'code,name', $story->EditElements('featured'))
                          . "</select>" . LB;
    } else {
        $featured_options = "<input type=\"hidden\" name=\"featured\" value=\"0\"" . XHTML . ">";
    }
    $T->set_var(array(
        'featured_options'      => $featured_options,
        'frontpage_options'     => COM_optionList($_TABLES['frontpagecodes'],
                            'code,name', $story->EditElements('frontpage')),
        'story_introtext'       => $story->EditElements('introtext'),
        'story_bodytext'        => $story->EditElements('bodytext'),
        'no_javascript_return_link' => sprintf($LANG_ARTICLE['no_js_link'],
                                    ARTICLE_ADMIN_URL.'/index.php',$sid),
    ) );
    //$T->set_var('lang_introtext', $LANG24[16],
    //$T->set_var('lang_bodytext', $LANG24[17]);
    //$T->set_var('lang_postmode', $LANG24[4]);
    //$T->set_var('lang_publishoptions',$LANG24[76]);
    //$T->set_var('lang_nojavascript',$LANG24[77]);
    $post_options = COM_optionList($_TABLES['postmodes'],'code,name',$story->EditElements('postmode'));

    // If Advanced Mode - add post option and set default if editing story created with Advanced Editor
    if ($_CONF['advanced_editor'] == 1) {
        if ($story->EditElements('advanced_editor_mode') == 1 OR $story->EditElements('postmode') == 'adveditor') {
            $post_options .= '<option value="adveditor" selected="selected">'.$LANG24[86].'</option>';
        } else {
            $post_options .= '<option value="adveditor">'.$LANG24[86].'</option>';
        }
    }
    if ($_CONF['wikitext_editor']) {
        if ($story->EditElements('postmode') == 'wikitext') {
            $post_options .= '<option value="wikitext" selected="selected">'.$LANG24[88].'</option>';
        } else {
            $post_options .= '<option value="wikitext">'.$LANG24[88].'</option>';
        }
    }
    $T->set_var('post_options',$post_options );
    $T->set_var('lang_allowed_html', COM_allowedHTML());
    if ($story->EditElements('advanced_editor_mode') == 1 || 
            $story->EditElements('postmode') == 'adveditor' ||
            $story->EditElements('postmode') == 'plaintext') {
        $T->set_var ('show_allowedhtml', 'none');
    } else {
        $T->set_var ('show_allowedhtml', '');
    }

    $fileinputs = '';
    $saved_images = '';
    if ($_CONF['maximagesperarticle'] > 0) {
        $T->set_var('lang_images', $LANG24[47]);
        $icount = DB_count($_TABLES['article_images'],'ai_sid', $story->getSid());
        if ($icount > 0) {
            $result_articles = DB_query("SELECT * FROM {$_TABLES['article_images']} WHERE ai_sid = '".$story->getSid()."'");
            for ($z = 1; $z <= $icount; $z++) {
                $I = DB_fetchArray($result_articles);
                $saved_images .= $z . ') '
                    . COM_createLink($I['ai_filename'],
                        $_CONF['site_url'] . '/images/articles/' . $I['ai_filename'])
                    . '&nbsp;&nbsp;&nbsp;' . $LANG_ADMIN['delete']
                    . ': <input type="checkbox" name="delete[' .$I['ai_img_num']
                    . ']"' . XHTML . '><br' . XHTML . '>';
            }
        }

        $newallowed = $_CONF['maximagesperarticle'] - $icount;
        for ($z = $icount + 1; $z <= $_CONF['maximagesperarticle']; $z++) {
            $fileinputs .= $z . ') <input type="file" dir="ltr" name="file[]'
                        . '"' . XHTML . '>';
            if ($z < $_CONF['maximagesperarticle']) {
                $fileinputs .= '<br' . XHTML . '>';
            }
        }
        $fileinputs .= '<br' . XHTML . '>' . $LANG24[51];
        if ($_CONF['allow_user_scaling'] == 1) {
            $fileinputs .= $LANG24[27];
        }
        $fileinputs .= $LANG24[28] . '<br' . XHTML . '>';
    }
    $T->set_var('saved_images', $saved_images);
    $T->set_var('image_form_elements', $fileinputs);
    $T->set_var('lang_hits', $LANG24[18]);
    $T->set_var('story_hits', $story->EditElements('hits'));
    //$T->set_var('lang_comments', $LANG24[19]);
    $T->set_var('story_comments', $story->EditElements('comments'));
    //$T->set_var('lang_trackbacks', $LANG24[29]);
    $T->set_var('story_trackbacks', $story->EditElements('trackbacks'));
    //$T->set_var('lang_emails', $LANG24[39]);
    $T->set_var('story_emails', $story->EditElements('numemails'));

    if ( $_CONF['rating_enabled'] ) {
        $rating = @number_format($story->EditElements('rating'),2);
        $votes  = $story->EditElements('votes');
        $T->set_var('rating',$rating);
        $T->set_var('votes',$votes);
    }

    $T->set_var('story_id', $story->getSid());
    $T->set_var('old_story_id', $story->EditElements('originalSid'));
    //$T->set_var('lang_sid', $LANG24[12]);
    $T->set_var('lang_save', $saveoption);
    //$T->set_var('lang_preview', $LANG_ADMIN['preview']);
    //$T->set_var('lang_cancel', $LANG_ADMIN['cancel']);
    //$T->set_var('lang_delete', $LANG_ADMIN['delete']);
    $T->set_var('gltoken_name', CSRF_TOKEN);
    $T->set_var('gltoken', SEC_createToken());
    $T->parse('output','editor');
    $display .= $T->finish($T->get_var('output'));
    $display .= COM_endBlock (COM_getBlockTemplate ('_admin_block', 'footer'));

    return $display;
}




?>
