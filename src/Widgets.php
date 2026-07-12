<?php

declare(strict_types=1);

namespace Dotclear\Plugin\lastpostsExtend;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
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

        $_ = $w->get('lastpostsextend');
        if (!($_ instanceof WidgetsElement)) {
            return;
        }
        
        // Title
        $_->addTitle(__('Last entries'));

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
                    if (is_array($v) && is_string($v['name'])) {
                        $posttypes[$v['name']] = $k;
                    }

                }
            }
        }
        $_->setting(
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
                $rs->intField('level') - 1
            ) . '&bull; ' . Html::escapeHTML($rs->strField('cat_title'))] = $rs->intField('cat_id');
        }
        $_->setting(
            'category',
            __('Category:'),
            '',
            'combo',
            $categories
        );
        unset($rs, $categories);

        // Passworded
        $_->setting(
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
        $_->setting(
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
        $_->setting(
            'selectedonly',
            __('Selected entries only'),
            0,
            'check'
        );

        // Updated entries only
        $_->setting(
            'updatedonly',
            __('Updated entries only'),
            0,
            'check'
        );

        // Tag
        if (App::plugins()->moduleExists('tags')) {
            $_->setting(
                'tag',
                __('Limit to tags:'),
                '',
                'text'
            );
        }

        // Search
        $_->setting(
            'search',
            __('Limit to words:'),
            '',
            'text'
        );

        // Entries limit
        $_->setting(
            'limit',
            __('Entries limit:'),
            10,
            'text'
        );

        // Sort
        $_->setting(
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
        $_->setting(
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
        $_->setting(
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
        $_->setting(
            'excerpt',
            __('Show entries excerpt'),
            0,
            'check'
        );

        // Excerpt cut length
        $_->setting(
            'excerptlen',
            __('Excerpt length:'),
            100,
            'text'
        );

        // Comment count
        $_->setting(
            'commentscount',
            __('Show comments count'),
            0,
            'check'
        );

        // commons
        $_
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
            || !$w->checkHomeOnly(App::url()->getType())
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
                'post_upddt ' : (is_string($w->get('sortby')) ? $w->get('sortby') : '') . ' ';
        } else {
            $params['order'] = $w->get('sortby') == 'date' ?
                'post_dt ' : (is_string($w->get('sortby')) ? $w->get('sortby') : '') . ' ';
        }
        $params['order'] .= $w->get('sort') == 'asc' ? 'asc' : 'desc';
        $params['limit']      = is_numeric($w->get('limit')) ? abs((int) $w->get('limit')) : 10;
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
            $tags = explode(',', is_string($w->get('tag')) ? $w->get('tag') : '');
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
        if (!($rs instanceof MetaRecord) || $rs->isEmpty()) {
            return '';
        }

        // Parse result
        $res = is_string($w->get('title')) ? $w->renderTitle(Html::escapeHTML($w->get('title'))) : '';

        while ($rs->fetch()) {
            $published = $rs->intField('post_status') == App::blog()::POST_PUBLISHED;
            $df = App::blog()->settings()->get('system')->get('date_format');
            $tf = App::blog()->settings()->get('system')->get('time_format');

            $res .= '<li>' .
            '<' . ($published ? 'a href="' . $rs->getURL() . '"' : 'span') .
            ' title="' .
            Date::dt2str(
                is_string($df) ? $df : '',
                $rs->strField('post_upddt')
            ) . ', ' .
            Date::dt2str(
                is_string($tf) ? $tf : '',
                $rs->strField('post_upddt')
            ) . '">' .
            Html::escapeHTML($rs->strField('post_title')) .
            '</' . ($published ? 'a' : 'span') . '>';

            // Nb comments
            if ($w->get('commentscount') && $published) {
                $res .= ' (' . $rs->strField('nb_comment') . ')';
            }

            // First image
            if ($w->get('firstimage') != '') {
                $res .= self::entryFirstImage(
                    $rs->strField('post_type'),
                    $rs->intField('post_id'),
                    is_string($w->get('firstimage')) ? $w->get('firstimage') : 's'
                );
            }

            // Excerpt
            if ($w->get('excerpt')) {
                $excerpt = $rs->strField('post_excerpt');
                if ($rs->strField('post_format') == 'wiki') {
                    App::filter()->initWikiComment();
                    $excerpt = App::filter()->wikiTransform($excerpt);
                    $excerpt = App::filter()->HTMLfilter($excerpt);
                }
                if (strlen($excerpt) > 0) {
                    $cut = Text::cutString(
                        $excerpt,
                        is_numeric($w->get('excerptlen')) ? abs((int) $w->get('excerptlen')) : 80
                    );
                    $res .= ' : ' . $cut . (strlen($cut) < strlen($excerpt) ? '...' : '');

                    unset($cut);
                }
            }
            $res .= '</li>';
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'lastpostsextend ' . (is_string($w->get('class')) ? $w->get('class') : ''),
            '',
            '<ul>' . $res . '</ul>'
        );
    }

    private static function entryFirstImage(string $type, int $id, string $size = 's'): string
    {
        if (!App::blog()->isDefined() || !in_array($type, ['post', 'page', 'galitem'])) {
            return '';
        }

        $rs = App::auth()->sudo(
            App::blog()->getPosts(...),
            ['post_id' => $id, 'post_type' => $type],
            false
        );

        if (!($rs instanceof MetaRecord) || $rs->isEmpty()) {
            return '';
        }

        if (!preg_match('/^sq|t|s|m|o$/', $size)) {
            $size = 's';
        }

        $p_url  = App::blog()->settings()->get('system')->get('public_url');
        $p_url = is_string($p_url) ? $p_url : '';
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

        $subject = $rs->strField('post_excerpt_xhtml') . $rs->strField('post_content_xhtml') . $rs->strField('cat_desc');
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
        '<a title="' . Html::escapeHTML($rs->strField('post_title')) . '" href="' . $rs->getURL() . '">' .
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
