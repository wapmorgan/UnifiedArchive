<?php
namespace wapmorgan\UnifiedArchive\Drivers\OneFile;

use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\Drivers\OneFile\OneFileDriver;

/**
 * Class Lzma
 *
 * @package wapmorgan\UnifiedArchive\Formats
 * @requires ext-lzma2
 */
class Lzma extends OneFileDriver
{
    const FORMAT_SUFFIX =  'xz';

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::LZMA,
        ];
    }

    /**
     * @param $format
     * @return bool
     */
    public static function checkFormatSupport($format)
    {
        if (!extension_loaded('xz')) {
            return [];
        }
        switch ($format) {
            case Formats::LZMA:
                return [Formats::OPEN, Formats::EXTRACT_CONTENT, Formats::STREAM_CONTENT, Formats::CREATE];
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'adapter for ext-xz';
    }

    /**
     * @inheritDoc
     */
    public static function getInstallationInstruction()
    {
        return !extension_loaded('xz')
            ? 'install [xz] extension' . "\n" . 'For 5.x: https://github.com/payden/php-xz' . "\n" . 'For 7.x: https://github.com/codemasher/php-ext-xz'
            : null;
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        parent::__construct($archiveFileName, $password);
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
    public function getFileStream($fileName = null)
    {
        return xzopen($this->fileName, 'r');
    }

    /**
     * @param $data
     * @param $compressionLevel
     * @return mixed|string
     */
    protected static function compressData($data, $compressionLevel)
    {
        $fp = xzopen('php://temp', 'w');
        xzwrite($fp, $data);
        $data = stream_get_contents($fp);
        xzclose($fp);
        return $data;
    }
}