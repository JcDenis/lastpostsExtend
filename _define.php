<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of lastpostsExtend, a plugin for Dotclear 2.
# 
# Copyright (c) 2009-2021 Jean-Christian Denis and contributors
# 
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_RC_PATH')) {
    return null;
}

$this->registerModule(
    'Last entries (Extended)',
    'Extended list of entries',
    'Jean-Christian Denis and contributors',
    '2021.08.25',
    [
        'permissions' => 'admin',
        'type' => 'plugin',
        'dc_min' => '2.19',
        'support' => 'https://github.com/JcDenis/lastpostsExtend',
        'details' => 'http://plugins.dotaddict.org/dc2/details/lastpostsExtend',
        'repository' => 'https://raw.githubusercontent.com/JcDenis/lastpostsExtend/master/repository.xml'
    ]
);