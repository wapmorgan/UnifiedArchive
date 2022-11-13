<?php
namespace wapmorgan\UnifiedArchive\Drivers\OneFile;

use Exception;
use wapmorgan\UnifiedArchive\Formats;

class Gzip extends OneFileDriver
{
    const EXTENSION_NAME = 'zlib';
    const FORMAT = Formats::GZIP;

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'adapter for ext-zlib'.(defined('ZLIB_VERSION') ? ' ('.ZLIB_VERSION.')' : null);
    }

    /**
     * @param string $file GZipped file
     * @return array|false Array with 'mtime' and 'size' items
     */
    public static function gzipStat($file)
    {
        $fp = fopen($file, 'rb');
        if (filesize($file) < 18 || strcmp(fread($fp, 2), "\x1f\x8b")) {
            return false;  // Not GZIP format (See RFC 1952)
        }
        $method = fread($fp, 1);
        $flags = fread($fp, 1);
        $stat = unpack('Vmtime', fread($fp, 4));
        fseek($fp, -4, SEEK_END);
        $stat += unpack('Vsize', fread($fp, 4));
        fclose($fp);

        return $stat;
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        parent::__construct($archiveFileName, $format, $password);
        $stat = static::gzipStat($archiveFileName);
        if ($stat === false) {
            throw new Exception('Could not open Gzip file');
        }
        $this->uncompressedSize = $stat['size'];
        $this->modificationTime = $stat['mtime'];
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     */
    public function getFileContent($fileName = null)
    {
        return gzdecode(file_get_contents($this->fileName));
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileStream($fileName = null)
    {
        return gzopen($this->fileName, 'rb');
    }

    /**
     * @param $data
     * @param $compressionLevel
     * @return mixed|string
     */
    protected static function compressData($data, $compressionLevel)
    {
        static $compressionLevelMap = [
            self::COMPRESSION_NONE => 0,
            self::COMPRESSION_WEAK => 2,
            self::COMPRESSION_AVERAGE => 4,
            self::COMPRESSION_STRONG => 7,
            self::COMPRESSION_MAXIMUM => 9,
        ];
        return gzencode($data, $compressionLevelMap[$compressionLevel]);
    }
}
