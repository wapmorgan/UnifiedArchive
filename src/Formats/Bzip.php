<?php
namespace wapmorgan\UnifiedArchive\Formats;

class Bzip extends OneFileFormat
{
    const FORMAT_SUFFIX =  'bz2';

    /**
     * Bzip constructor.
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
     * @param $data
     *
     * @return mixed|string
     */
    protected static function compressData($data)
    {
        return bzcompress($data);
    }
}