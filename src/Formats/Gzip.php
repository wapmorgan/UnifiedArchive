<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Exception;

class Gzip extends OneFileFormat
{
    const FORMAT_SUFFIX = 'gz';

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
     * Gzip constructor.
     *
     * @param $archiveFileName
     *
     * @throws \Exception
     */
    public function __construct($archiveFileName)
    {
        parent::__construct($archiveFileName);
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
    public function getFileResource($fileName = null)
    {
        return gzopen($this->fileName, 'rb');
    }

    /**
     * @param $data
     *
     * @return mixed|string
     */
    protected static function compressData($data)
    {
        return gzencode($data);
    }
}