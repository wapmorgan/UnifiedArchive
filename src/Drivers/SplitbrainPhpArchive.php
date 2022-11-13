<?php

namespace wapmorgan\UnifiedArchive\Drivers;

use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicPureDriver;
use wapmorgan\UnifiedArchive\Formats;

class SplitbrainPhpArchive extends BasicPureDriver
{
    const PACKAGE_NAME = 'splitbrain/php-archive';
    const MAIN_CLASS = '\\splitbrain\\PHPArchive\\Tar';

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'php-library for zip/tar (with gzip/bzip-compression)';
    }

    /**
     * @inheritDoc
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::ZIP,
            Formats::TAR,
            Formats::TAR_GZIP,
            Formats::TAR_BZIP,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function checkFormatSupport($format)
    {
        if (!static::isInstalled()) {
            return [];
        }

        if (
            ($format === Formats::TAR_BZIP && !extension_loaded('bzip2'))
            || ($format === Formats::TAR_GZIP && !extension_loaded('zlib'))
        ) {
            return [];
        }

        switch ($format) {
            case Formats::ZIP:
            case Formats::TAR:
            case Formats::TAR_GZIP;
            case Formats::TAR_BZIP;
                return [
                    BasicDriver::OPEN,
//                    BasicDriver::EXTRACT_CONTENT,
                    BasicDriver::APPEND,
                    BasicDriver::CREATE,
                    BasicDriver::CREATE_ENCRYPTED,
                    BasicDriver::CREATE_IN_STRING,
                ];
        }
    }

    /**
     * @inheritDoc
     */
    public function getArchiveInformation()
    {
        // TODO: Implement getArchiveInformation() method.
    }

    /**
     * @inheritDoc
     */
    public function getFileNames()
    {
        // TODO: Implement getFileNames() method.
    }

    /**
     * @inheritDoc
     */
    public function isFileExists($fileName)
    {
        // TODO: Implement isFileExists() method.
    }

    /**
     * @inheritDoc
     */
    public function getFileData($fileName)
    {
        // TODO: Implement getFileData() method.
    }

    /**
     * @inheritDoc
     */
    public function getFileContent($fileName)
    {
        // TODO: Implement getFileContent() method.
    }

    /**
     * @inheritDoc
     */
    public function getFileStream($fileName)
    {
        // TODO: Implement getFileStream() method.
    }

    /**
     * @inheritDoc
     */
    public function extractFiles($outputFolder, array $files)
    {
        // TODO: Implement extractFiles() method.
    }

    /**
     * @inheritDoc
     */
    public function extractArchive($outputFolder)
    {
        // TODO: Implement extractArchive() method.
    }
}
