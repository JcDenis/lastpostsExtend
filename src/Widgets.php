<?php

declare(strict_types=1);

namespace Dotclear\Plugin\lastpostsExtend;

use Dotclear\App;
use Dotclear\Helper\Date;
use Dotclear\Helper\Text;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\widgets\WidgetsStack;
use Dotclear\Plugin\widgets\WidgetsElement;

/**
 * @brief       lastpostsExtend widgets class.
 * @ingroup     lastpostsExtend
 *
 * @author      Jean-Christian Denis (author)
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Widgets
{
    public static function initWidgets(WidgetsStack $w): void
    {
        if (!App::blog()->isDefined()) {
            return;
        }

        // Create widget
        $w->create(
            'lastpostsextend',
            __('Last entries (Extended)'),
            self::parseWidget(...),
            null,
            __('Extended list of entries')
        );
        // Title
        $w->get('lastpostsextend')->addTitle(__('Last entries'));

        // post type
        $posttypes = [
            __('Post')    => 'post',
            __('Page')    => 'page',
            __('Gallery') => 'galitem',
        ];
        // plugin muppet types
        if (App::plugins()->getDefine('muppet')->isDefined() && class_exists('\muppet')) {
            $muppet_types = \muppet::getPostTypes();
            if (is_array($muppet_types) && !empty($muppet_types)) {
                foreach ($muppet_types as $k => $v) {
                    $posttypes[$v['name']] = $k;
                }
            }
        }
        $w->get('lastpostsextend')->setting(
            'posttype',
            __('Type:'),
            'post',
            'combo',
            $posttypes
        );

        // Category (post and page have same category)
        $rs = App::blog()->getCategories([
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
        $w->get('lastpostsextend')->setting(
            'category',
            __('Category:'),
            '',
            'combo',
            $categories
        );
        unset($rs, $categories);

        // Passworded
        $w->get('lastpostsextend')->setting(
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

        // Status
        $w->get('lastpostsextend')->setting(
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

        // Selected entries only
        $w->get('lastpostsextend')->setting(
            'selectedonly',
            __('Selected entries only'),
            0,
            'check'
        );

        // Updated entries only
        $w->get('lastpostsextend')->setting(
            'updatedonly',
            __('Updated entries only'),
            0,
            'check'
        );

        // Tag
        if (App::plugins()->moduleExists('tags')) {
            $w->get('lastpostsextend')->setting(
                'tag',
                __('Limit to tags:'),
                '',
                'text'
            );
        }

        // Search
        $w->get('lastpostsextend')->setting(
            'search',
            __('Limit to words:'),
            '',
            'text'
        );

        // Entries limit
        $w->get('lastpostsextend')->setting(
            'limit',
            __('Entries limit:'),
            10,
            'text'
        );

        // Sort
        $w->get('lastpostsextend')->setting(
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
        $w->get('lastpostsextend')->setting(
            'sort',
            __('Sort:'),
            'desc',
            'combo',
            [
                __('Ascending')  => 'asc',
                __('Descending') => 'desc',
            ]
        );

        // First image
        $w->get('lastpostsextend')->setting(
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

        // With excerpt
        $w->get('lastpostsextend')->setting(
            'excerpt',
            __('Show entries excerpt'),
            0,
            'check'
        );

        // Excerpt cut length
        $w->get('lastpostsextend')->setting(
            'excerptlen',
            __('Excerpt length:'),
            100,
            'text'
        );

        // Comment count
        $w->get('lastpostsextend')->setting(
            'commentscount',
            __('Show comments count'),
            0,
            'check'
        );

        // commons
        $w->get('lastpostsextend')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    public static function parseWidget(WidgetsElement $w): string
    {
        // Widget is offline & Home page only
        if (!App::blog()->isDefined()
            || $w->get('offline')
            || !$w->checkHomeOnly(App::url()->type)
        ) {
            return '';
        }

        // Need posts excerpt
        if ($w->get('excerpt')) {
            $params['columns'][] = 'post_excerpt';
        }

        // prepare request params
        $params = [
            'sql'     => '',
            'columns' => [],
            'from'    => '',
        ];

        // Passworded
        if ($w->get('passworded') == 'yes') {
            $params['sql'] .= 'AND post_password IS NOT NULL ';
        } elseif ($w->get('passworded') == 'no') {
            $params['sql'] .= 'AND post_password IS NULL ';
        }

        // Status
        if ($w->get('status') != 'all') {
            $params['post_status'] = $w->get('status');
        }

        // Search words
        if ('' != $w->get('search')) {
            $params['search'] = $w->get('search');
        }

        // Updated posts only
        if ($w->get('updatedonly')) {
            $params['sql'] .= 'AND post_creadt < post_upddt ' .
                'AND post_dt < post_upddt ';
            /*
                        $params['sql'] .=
                        "AND TIMESTAMP(post_creadt ,'DD-MM-YYYY HH24:MI:SS') < TIMESTAMP(post_upddt ,'DD-MM-YYYY HH24:MI:SS') ".
                        "AND TIMESTAMP(post_dt ,'DD-MM-YYYY HH24:MI:SS') < TIMESTAMP(post_upddt ,'DD-MM-YYYY HH24:MI:SS') ";
            //*/
            $params['order'] = $w->get('sortby') == 'date' ?
                'post_upddt ' : $w->get('sortby') . ' ';
        } else {
            $params['order'] = $w->get('sortby') == 'date' ?
                'post_dt ' : $w->get('sortby') . ' ';
        }
        $params['order'] .= $w->get('sort') == 'asc' ? 'asc' : 'desc';
        $params['limit']      = abs((int) $w->get('limit'));
        $params['no_content'] = true;

        // Selected posts only
        if ($w->get('selectedonly')) {
            $params['post_selected'] = 1;
        }

        // Post type
        $params['post_type'] = $w->get('posttype');

        // Category
        if ($w->get('category')) {
            if ($w->get('category') == 'null') {
                $params['sql'] .= ' AND P.cat_id IS NULL ';
            } elseif (is_numeric($w->get('category'))) {
                $params['cat_id'] = (int) $w->get('category');
            } else {
                $params['cat_url'] = $w->get('category');
            }
        }

        // Tags
        if (App::plugins()->moduleExists('tags') && $w->get('tag')) {
            $tags = explode(',', $w->get('tag'));
            foreach ($tags as $i => $tag) {
                $tags[$i] = trim($tag);
            }
            $params['from'] .= ', ' . App::db()->con()->prefix() . App::meta()::META_TABLE_NAME . ' META ';
            $params['sql']  .= 'AND META.post_id = P.post_id ';
            $params['sql']  .= 'AND META.meta_id ' . App::db()->con()->in($tags) . ' ';
            $params['sql']  .= "AND META.meta_type = 'tag' ";
        }

        $rs = App::auth()->sudo(
            App::blog()->getPosts(...),
            $params,
            false
        );

        // No result
        if ($rs->isEmpty()) {
            return '';
        }

        // Parse result
        $res = $w->get('title') ? $w->renderTitle(Html::escapeHTML($w->get('title'))) : '';

        while ($rs->fetch()) {
            $published = ((int) $rs->f('post_status')) == App::blog()::POST_PUBLISHED;

            $res .= '<li>' .
            '<' . ($published ? 'a href="' . $rs->getURL() . '"' : 'span') .
            ' title="' .
            Date::dt2str(
                App::blog()->settings()->get('system')->get('date_format'),
                $rs->f('post_upddt')
            ) . ', ' .
            Date::dt2str(
                App::blog()->settings()->get('system')->get('time_format'),
                $rs->f('post_upddt')
            ) . '">' .
            Html::escapeHTML($rs->f('post_title')) .
            '</' . ($published ? 'a' : 'span') . '>';

            // Nb comments
            if ($w->get('commentscount') && $published) {
                $res .= ' (' . $rs->nb_comment . ')';
            }

            // First image
            if ($w->get('firstimage') != '') {
                $res .= self::entryFirstImage(
                    $rs->f('post_type'),
                    $rs->f('post_id'),
                    $w->get('firstimage')
                );
            }

            // Excerpt
            if ($w->get('excerpt')) {
                $excerpt = $rs->f('post_excerpt');
                if ($rs->f('post_format') == 'wiki') {
                    App::filter()->initWikiComment();
                    $excerpt = App::filter()->wikiTransform($excerpt);
                    $excerpt = App::filter()->HTMLfilter($excerpt);
                }
                if (strlen($excerpt) > 0) {
                    $cut = Text::cutString(
                        $excerpt,
                        abs((int) $w->get('excerptlen'))
                    );
                    $res .= ' : ' . $cut . (strlen($cut) < strlen($excerpt) ? '...' : '');

                    unset($cut);
                }
            }
            $res .= '</li>';
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'lastpostsextend ' . $w->get('class'),
            '',
            '<ul>' . $res . '</ul>'
        );
    }

    private static function entryFirstImage(string $type, string $id, string $size = 's'): string
    {
        if (!App::blog()->isDefined() || !in_array($type, ['post', 'page', 'galitem'])) {
            return '';
        }

        $rs = App::auth()->sudo(
            App::blog()->getPosts(...),
            ['post_id' => $id, 'post_type' => $type],
            false
        );

        if ($rs->isEmpty()) {
            return '';
        }

        if (!preg_match('/^sq|t|s|m|o$/', $size)) {
            $size = 's';
        }

        $p_url  = (string) App::blog()->settings()->get('system')->get('public_url');
        $p_site = (string) preg_replace(
            '#^(.+?//.+?)/(.*)$#',
            '$1',
            App::blog()->url()
        );
        $p_root = App::blog()->publicPath();

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
