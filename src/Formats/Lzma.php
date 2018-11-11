<?php
namespace wapmorgan\UnifiedArchive\Formats;

/**
 * Class Lzma
 *
 * @package wapmorgan\UnifiedArchive\Formats
 * @requires ext-lzma2
 */
class Lzma extends OneFileFormat
{
    const FORMAT_SUFFIX =  'xz';

    /**
     * Lzma constructor.
     *
     * @param $archiveFileName
     *
     * @throws \Exception
     */
    public function __construct($archiveFileName)
    {
        parent::__construct($archiveFileName);
        $this->modificationTime = filemtime($this->fileName);
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     */
    public function getFileContent($fileName = null)
    {
        return stream_get_contents(xzopen($this->fileName, 'r'));
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileResource($fileName = null)
    {
        return xzopen($this->fileName, 'r');
    }

    /**
     * @param $data
     *
     * @return mixed|string
     */
    protected static function compressData($data)
    {
        $fp = xzopen('php://temp', 'w');
        xzwrite($fp, $data);
        $data = stream_get_contents($fp);
        xzclose($fp);
        return $data;
    }
}