<?php
namespace wapmorgan\UnifiedArchive\Drivers\OneFile;

use wapmorgan\UnifiedArchive\Formats;

class Bzip extends OneFileDriver
{
    const EXTENSION_NAME = 'bz2';
    const FORMAT = Formats::BZIP;

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::BZIP,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'adapter for ext-bzip2'.(static::isInstalled() ? ' ('.phpversion(static::EXTENSION_NAME).')' : null);
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        parent::__construct($archiveFileName, $format, $password);
        $this->modificationTime = filemtime($this->fileName);
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     */
    public function getFileContent($fileName = null)
    {
        return bzdecompress(file_get_contents($this->fileName));
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileStream($fileName = null)
    {
        return bzopen($this->fileName, 'r');
    }

    /**
     * @param string $data
     * @param int $compressionLevel
     * @return mixed|string
     */
    protected static function compressData($data, $compressionLevel)
    {
        static $compressionLevelMap = [
            self::COMPRESSION_NONE => 1,
            self::COMPRESSION_WEAK => 2,
            self::COMPRESSION_AVERAGE => 4,
            self::COMPRESSION_STRONG => 7,
            self::COMPRESSION_MAXIMUM => 9,
        ];
        static $work_factor_multiplier = 27;

        // it seems not working at all
        $work_factor = ($compressionLevelMap[$compressionLevel] * $work_factor_multiplier);
        return bzcompress($data, $compressionLevelMap[$compressionLevel], $work_factor);
    }
}
