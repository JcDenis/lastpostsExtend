<?php
/**
 * @file
 * @brief       The plugin lastpostsExtend definition
 * @ingroup     lastpostsExtend
 *
 * @defgroup    lastpostsExtend Plugin lastpostsExtend.
 *
 * Extended list of entries.
 *
 * @author      Jean-Christian Denis (author)
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

$this->registerModule(
    'Last entries (Extended)',
    'Extended list of entries',
    'Jean-Christian Denis and contributors',
    '2025.09.13',
    [
        'requires'    => [['core', '2.36']],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/JcDenis/' . $this->id . '/issues',
        'details'     => 'https://github.com/JcDenis/' . $this->id . '/',
        'repository'  => 'https://raw.githubusercontent.com/JcDenis/' . $this->id . '/master/dcstore.xml',
        'date'        => '2025-09-13T14:56:05+00:00',
    ]
);
