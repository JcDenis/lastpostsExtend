<?php
/**
 * @brief lastpostsExtend, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugin
 *
 * @author Jean-Christian Denis and contributors
 *
 * @copyright Jean-Christian Denis
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\lastpostsExtend;

use dcBlog;
use dcCore;
use dcMeta;
use Dotclear\Helper\Date;
use Dotclear\Helper\Text;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\widgets\WidgetsStack;
use Dotclear\Plugin\widgets\WidgetsElement;

class Widgets
{
    public static function initWidgets(WidgetsStack $w): void
    {
        # Create widget
        $w->create(
            'lastpostsextend',
            __('Last entries (Extended)'),
            [self::class, 'parseWidget'],
            null,
            __('Extended list of entries')
        );
        # Title
        $w->lastpostsextend->addTitle(__('Last entries'));
        # type
        $posttypes = [
            __('Post')    => 'post',
            __('Page')    => 'page',
            __('Gallery') => 'galitem',
        ];
        # plugin muppet types
        if (dcCore::app()->plugins->moduleExists('muppet')) {
            $muppet_types = muppet::getPostTypes();
            if (is_array($muppet_types) && !empty($muppet_types)) {
                foreach ($muppet_types as $k => $v) {
                    $posttypes[$v['name']] = $k;
                }
            }
        }
        $w->lastpostsextend->setting(
            'posttype',
            __('Type:'),
            'post',
            'combo',
            $posttypes
        );
        # Category (post and page have same category)
        $rs = dcCore::app()->blog->getCategories([
            'post_type' => 'post',
        ]);
        $categories = [
            ''                  => '',
            __('Uncategorized') => 'null',
        ];
        while ($rs->fetch()) {
            $categories[str_repeat(
                '&nbsp;&nbsp;',
                (int) $rs->f('level') - 1
            ) . '&bull; ' . Html::escapeHTML($rs->f('cat_title'))] = $rs->f('cat_id');
        }
        $w->lastpostsextend->setting(
            'category',
            __('Category:'),
            '',
            'combo',
            $categories
        );
        unset($rs, $categories);
        # Pasworded
        $w->lastpostsextend->setting(
            'passworded',
            __('Protection:'),
            'no',
            'combo',
            [
                __('all')                   => 'all',
                __('only without password') => 'no',
                __('only with password')    => 'yes',
            ]
        );
        # Status
        $w->lastpostsextend->setting(
            'status',
            __('Status:'),
            '1',
            'combo',
            [
                __('all')         => 'all',
                __('pending')     => '-2',
                __('scheduled')   => '-1',
                __('unpublished') => '0',
                __('published')   => '1',
            ]
        );
        # Selected entries only
        $w->lastpostsextend->setting(
            'selectedonly',
            __('Selected entries only'),
            0,
            'check'
        );
        # Updated entries only
        $w->lastpostsextend->setting(
            'updatedonly',
            __('Updated entries only'),
            0,
            'check'
        );
        # Tag
        if (dcCore::app()->plugins->moduleExists('tags')) {
            $w->lastpostsextend->setting(
                'tag',
                __('Limit to tags:'),
                '',
                'text'
            );
        }
        # Search
        $w->lastpostsextend->setting(
            'search',
            __('Limit to words:'),
            '',
            'text'
        );
        # Entries limit
        $w->lastpostsextend->setting(
            'limit',
            __('Entries limit:'),
            10,
            'text'
        );
        # Sort type
        $w->lastpostsextend->setting(
            'sortby',
            __('Order by:'),
            'date',
            'combo',
            [
                __('Date')     => 'date',
                __('Title')    => 'post_title',
                __('Comments') => 'nb_comment',
            ]
        );
        # Sort order
        $w->lastpostsextend->setting(
            'sort',
            __('Sort:'),
            'desc',
            'combo',
            [
                __('Ascending')  => 'asc',
                __('Descending') => 'desc',
            ]
        );
        # First image
        $w->lastpostsextend->setting(
            'firstimage',
            __('Show entries first image:'),
            '',
            'combo',
            [
                __('no')        => '',
                __('square')    => 'sq',
                __('thumbnail') => 't',
                __('small')     => 's',
                __('medium')    => 'm',
                __('original')  => 'o',
            ]
        );
        # With excerpt
        $w->lastpostsextend->setting(
            'excerpt',
            __('Show entries excerpt'),
            0,
            'check'
        );
        # Excerpt length
        $w->lastpostsextend->setting(
            'excerptlen',
            __('Excerpt length:'),
            100,
            'text'
        );
        # Comment count
        $w->lastpostsextend->setting(
            'commentscount',
            __('Show comments count'),
            0,
            'check'
        );
        # common
        $w->lastpostsextend
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    public static function parseWidget(WidgetsElement $w): string
    {
        $params = [
            'sql'     => '',
            'columns' => [],
            'from'    => '',
        ];

        # Widget is offline & Home page only
        if ($w->offline || !$w->checkHomeOnly(dcCore::app()->url->type)) {
            return '';
        }

        # Need posts excerpt
        if ($w->excerpt) {
            $params['columns'][] = 'post_excerpt';
        }

        # Passworded
        if ($w->passworded == 'yes') {
            $params['sql'] .= 'AND post_password IS NOT NULL ';
        } elseif ($w->passworded == 'no') {
            $params['sql'] .= 'AND post_password IS NULL ';
        }

        # Status
        if ($w->status != 'all') {
            $params['post_status'] = $w->status;
        }

        # Search words
        if ('' != $w->search) {
            $params['search'] = $w->search;
        }

        # Updated posts only
        if ($w->updatedonly) {
            $params['sql'] .= 'AND post_creadt < post_upddt ' .
                'AND post_dt < post_upddt ';
            /*
                        $params['sql'] .=
                        "AND TIMESTAMP(post_creadt ,'DD-MM-YYYY HH24:MI:SS') < TIMESTAMP(post_upddt ,'DD-MM-YYYY HH24:MI:SS') ".
                        "AND TIMESTAMP(post_dt ,'DD-MM-YYYY HH24:MI:SS') < TIMESTAMP(post_upddt ,'DD-MM-YYYY HH24:MI:SS') ";
            //*/
            $params['order'] = $w->sortby == 'date' ?
                'post_upddt ' : $w->sortby . ' ';
        } else {
            $params['order'] = $w->sortby == 'date' ?
                'post_dt ' : $w->sortby . ' ';
        }
        $params['order'] .= $w->sort == 'asc' ? 'asc' : 'desc';
        $params['limit']      = abs((int) $w->limit);
        $params['no_content'] = true;

        # Selected posts only
        if ($w->selectedonly) {
            $params['post_selected'] = 1;
        }

        # Type
        $params['post_type'] = $w->posttype;

        # Category
        if ($w->category) {
            if ($w->category == 'null') {
                $params['sql'] .= ' AND P.cat_id IS NULL ';
            } elseif (is_numeric($w->category)) {
                $params['cat_id'] = (int) $w->category;
            } else {
                $params['cat_url'] = $w->category;
            }
        }

        # Tags
        if (dcCore::app()->plugins->moduleExists('tags') && $w->tag) {
            $tags = explode(',', $w->tag);
            foreach ($tags as $i => $tag) {
                $tags[$i] = trim($tag);
            }
            $params['from'] .= ', ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' META ';
            $params['sql']  .= 'AND META.post_id = P.post_id ';
            $params['sql']  .= 'AND META.meta_id ' . dcCore::app()->con->in($tags) . ' ';
            $params['sql']  .= "AND META.meta_type = 'tag' ";
        }

        $rs = dcCore::app()->auth->sudo(
            [dcCore::app()->blog, 'getPosts'],
            $params,
            false
        );

        # No result
        if ($rs->isEmpty()) {
            return '';
        }

        # Return
        $res = $w->title ? $w->renderTitle(Html::escapeHTML($w->title)) : '';

        while ($rs->fetch()) {
            $published = ((int) $rs->f('post_status')) == dcBlog::POST_PUBLISHED;

            $res .= '<li>' .
            '<' . ($published ? 'a href="' . $rs->getURL() . '"' : 'span') .
            ' title="' .
            Date::dt2str(
                dcCore::app()->blog->settings->get('system')->get('date_format'),
                $rs->f('post_upddt')
            ) . ', ' .
            Date::dt2str(
                dcCore::app()->blog->settings->get('system')->get('time_format'),
                $rs->f('post_upddt')
            ) . '">' .
            Html::escapeHTML($rs->f('post_title')) .
            '</' . ($published ? 'a' : 'span') . '>';

            # Nb comments
            if ($w->commentscount && $published) {
                $res .= ' (' . $rs->nb_comment . ')';
            }

            # First image
            if ($w->firstimage != '') {
                $res .= self::entryFirstImage(
                    $rs->f('post_type'),
                    $rs->f('post_id'),
                    $w->firstimage
                );
            }

            # Excerpt
            if ($w->excerpt) {
                $excerpt = $rs->f('post_excerpt');
                if ($rs->f('post_format') == 'wiki') {
                    dcCore::app()->initWikiComment();
                    $excerpt = dcCore::app()->wikiTransform($excerpt);
                    $excerpt = dcCore::app()->HTMLfilter($excerpt);
                }
                if (strlen($excerpt) > 0) {
                    $cut = Text::cutString(
                        $excerpt,
                        abs((int) $w->excerptlen)
                    );
                    $res .= ' : ' . $cut . (strlen($cut) < strlen($excerpt) ? '...' : '');

                    unset($cut);
                }
            }
            $res .= '</li>';
        }

        return $w->renderDiv(
            (bool) $w->content_only,
            'lastpostsextend ' . $w->class,
            '',
            '<ul>' . $res . '</ul>'
        );
    }

    private static function entryFirstImage(string $type, int|string $id, string $size = 's'): string
    {
        if (!in_array($type, ['post', 'page', 'galitem'])) {
            return '';
        }

        $rs = dcCore::app()->auth->sudo(
            [dcCore::app()->blog, 'getPosts'],
            ['post_id' => $id, 'post_type' => $type],
            false
        );

        if ($rs->isEmpty()) {
            return '';
        }

        if (!preg_match('/^sq|t|s|m|o$/', $size)) {
            $size = 's';
        }

        $p_url  = dcCore::app()->blog->settings->get('system')->get('public_url');
        $p_site = preg_replace(
            '#^(.+?//.+?)/(.*)$#',
            '$1',
            dcCore::app()->blog->url
        );
        $p_root = dcCore::app()->blog->public_path;

        $pattern = '(?:' . preg_quote($p_site, '/') . ')?' . preg_quote($p_url, '/');
        $pattern = sprintf('/<img.+?src="%s(.*?\.(?:jpg|gif|png))"[^>]+/msu', $pattern);

        $src = '';
        $alt = '';

        $subject = $rs->f('post_excerpt_xhtml') . $rs->f('post_content_xhtml') . $rs->f('cat_desc');
        if (preg_match_all($pattern, $subject, $m) > 0) {
            foreach ($m[1] as $i => $img) {
                if (($src = self::ContentFirstImageLookup($p_root, $img, $size)) != '') {
                    $src = $p_url . (dirname($img) != '/' ? dirname($img) : '') . '/' . $src;
                    if (preg_match('/alt="([^"]+)"/', $m[0][$i], $malt)) {
                        $alt = $malt[1];
                    }

                    break;
                }
            }
        }

        if (!$src) {
            return '';
        }

        return
        '<div class="img-box">' .
        '<div class="img-thumbnail">' .
        '<a title="' . Html::escapeHTML($rs->f('post_title')) . '" href="' . $rs->getURL() . '">' .
        '<img alt="' . $alt . '" src="' . stripslashes($src) . '" />' .
        '</a></div>' .
        "</div>\n";
    }

    private static function ContentFirstImageLookup(string $root, string $img, string $size): string
    {
        $res = '';

        # Get base name and extension
        $info = Path::info($img);
        $base = $info['base'];

        if (preg_match('/^\.(.+)_(sq|t|s|m)$/', $base, $m)) {
            $base = $m[1];
        }

        if ($size != 'o' && file_exists($root . '/' . $info['dirname'] . '/.' . $base . '_' . $size . '.jpg')) {
            $res = '.' . $base . '_' . $size . '.jpg';
        } else {
            $f = $root . '/' . $info['dirname'] . '/' . $base;
            if (file_exists($f . '.' . $info['extension'])) {
                $res = $base . '.' . $info['extension'];
            } elseif (file_exists($f . '.jpg')) {
                $res = $base . '.jpg';
            } elseif (file_exists($f . '.png')) {
                $res = $base . '.png';
            } elseif (file_exists($f . '.gif')) {
                $res = $base . '.gif';
            }
        }

        return $res;
    }
}
