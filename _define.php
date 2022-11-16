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

if (!defined('DC_RC_PATH')) {
    return null;
}

$this->registerModule(
    'Last entries (Extended)',
    'Extended list of entries',
    'Jean-Christian Denis and contributors',
    '2022.11.12',
    [
        'requires' => [['core', '2.24']],
        'permissions' => dcAuth::PERMISSION_ADMIN,
        'type' => 'plugin',
        'support' => 'https://github.com/JcDenis/lastpostsExtend',
        'details' => 'http://plugins.dotaddict.org/dc2/details/lastpostsExtend',
        'repository' => 'https://raw.githubusercontent.com/JcDenis/lastpostsExtend/master/repository.xml'
    ]
);