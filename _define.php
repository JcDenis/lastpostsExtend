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
    '2023.10.19',
    [
        'requires'    => [['core', '2.28']],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/JcDenis/' . basename(__DIR__) . '/issues',
        'details'     => 'https://github.com/JcDenis/' . basename(__DIR__) . '/src/branch/master/README.md',
        'repository'  => 'https://github.com/JcDenis/' . basename(__DIR__) . '/raw/branch/master/dcstore.xml',
    ]
);
