<?php
/**
 *
 */

namespace Woody\Addon\AddonPolylang\Configurations;

class Services
{
    private static $definitions;

    private static function definitions()
    {
        return [];
    }

    public static function loadDefinitions()
    {
        if (!self::$definitions) {
            self::$definitions = self::definitions();
        }
        return self::$definitions;
    }
}
