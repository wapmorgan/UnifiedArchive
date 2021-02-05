<?php
namespace wapmorgan\UnifiedArchive\Formats;

class Bzip extends OneFileFormat
{
    const FORMAT_SUFFIX =  'bz2';

    /**
     * Bzip constructor.
     *
     * @param $archiveFileName
     * @param string|null $password
     * @throws \Exception
     */
    public function __construct($archiveFileName, $password = null)
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
        return bzdecompress(file_get_contents($this->fileName));
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileResource($fileName = null)
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
        // it seems not working at all
        $work_factor = ($compressionLevelMap[$compressionLevel] * 28);
        return bzcompress($data, $compressionLevelMap[$compressionLevel], $work_factor);
    }
}