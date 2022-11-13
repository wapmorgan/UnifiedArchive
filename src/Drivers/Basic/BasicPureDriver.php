<?php

namespace wapmorgan\UnifiedArchive\Drivers\Basic;

abstract class BasicPureDriver extends BasicDriver
{
    const TYPE = self::TYPE_PURE_PHP;
    const PACKAGE_NAME = null;
    const MAIN_CLASS = null;

    public static function isInstalled()
    {
        return class_exists(static::MAIN_CLASS);
    }

    public static function getInstallationInstruction()
    {
        return 'install library [ ' . static::PACKAGE_NAME . ']: `composer require ' . static::PACKAGE_NAME . '`';
    }
}
