<?php

declare(strict_types=1);

namespace Dotclear\Plugin\lastpostsExtend;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       lastpostsExtend backend class.
 * @ingroup     lastpostsExtend
 *
 * @author      Jean-Christian Denis (author)
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Backend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        App::behavior()->addBehavior('initWidgets', Widgets::initWidgets(...));

        return true;
    }
}
