<?php

namespace wapmorgan\UnifiedArchive\Drivers\Basic;

abstract class BasicExtensionDriver extends BasicDriver
{
    const TYPE = self::TYPE_EXTENSION;

    const EXTENSION_NAME = null;

    public static function isInstalled()
    {
        return extension_loaded(static::EXTENSION_NAME);
    }

    public static function getInstallationInstruction()
    {
        return 'install [' . static::EXTENSION_NAME . '] php extension';
    }
}
