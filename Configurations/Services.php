<?php
/**
 *
 */

namespace Woody\Lib\Polylang\Configurations;

class Services
{
    private static $definitions;

    private static function definitions()
    {
        return [
            'polylang.manager' => [
                'class'     => \Woody\Lib\Polylang\Services\PolylangManager::class,
                'arguments' => []
            ]
        ];
    }

    public static function loadDefinitions()
    {
        if (!self::$definitions) {
            self::$definitions = self::definitions();
        }

        return self::$definitions;
    }
}
