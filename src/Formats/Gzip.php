<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Exception;

class Gzip extends OneFileFormat
{
    const FORMAT_SUFFIX = 'gz';

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
        $stat = gzip_stat($archiveFileName);
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