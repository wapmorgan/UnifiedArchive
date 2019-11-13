<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\UnsupportedOperationException;

abstract class OneFileFormat extends BasicFormat
{
    /** @var null|string Should be filled for real format like 'gz' or other */
    const FORMAT_SUFFIX = null;

    protected $fileName;
    protected $inArchiveFileName;
    protected $uncompressedSize;
    protected $modificationTime;

    /**
     * BasicFormat constructor.
     *
     * @param string $archiveFileName
     *
     * @throws \Exception
     */
    public function __construct($archiveFileName)
    {
        if (static::FORMAT_SUFFIX === null)
            throw new \Exception('Format should be initialized');
        $this->fileName = $archiveFileName;
        $this->inArchiveFileName = basename($archiveFileName, '.'.self::FORMAT_SUFFIX);
    }

    /**
     * @return ArchiveInformation
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        $information->compressedFilesSize = filesize($this->fileName);
        $information->uncompressedFilesSize = $this->uncompressedSize;
        $information->files[] = $this->inArchiveFileName;
        return $information;
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        return [$this->inArchiveFileName];
    }

    /**
     * @param string $fileName
     *
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return $fileName === $this->inArchiveFileName;
    }

    /**
     * @param string $fileName
     *
     * @return ArchiveEntry|false
     */
    public function getFileData($fileName)
    {
        return new ArchiveEntry($this->inArchiveFileName, filesize($this->fileName),
            $this->uncompressedSize, $this->modificationTime);
    }

    /**
     * @param string $outputFolder
     * @param array  $files
     *
     * @return false|int
     * @throws \Exception
     */
    public function extractFiles($outputFolder, array $files = null)
    {
        return $this->extractArchive($outputFolder);
    }

    /**
     * @param string $outputFolder
     *
     * @return false|int
     * @throws \Exception
     */
    public function extractArchive($outputFolder)
    {
        $data = $this->getFileContent($this->inArchiveFileName);
        if ($data === false)
            throw new Exception('Could not extract archive');

        if (file_put_contents($outputFolder.$this->inArchiveFileName, $data) !== false)
            return 1;
    }

    /**
     * @param array $files
     *
     * @return false|int
     * @throws \wapmorgan\UnifiedArchive\UnsupportedOperationException
     */
    public function deleteFiles(array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param array $files
     *
     * @return false|int
     * @throws \wapmorgan\UnifiedArchive\UnsupportedOperationException
     */
    public function addFiles(array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param array  $files
     * @param string $archiveFileName
     *
     * @return false|int
     * @throws \wapmorgan\UnifiedArchive\UnsupportedOperationException
     */
    public static function createArchive(array $files, $archiveFileName){
        if (count($files) > 1) return false;
        $filename = array_shift($files);
        if (is_null($filename)) return false; // invalid list
        if (file_put_contents($archiveFileName,
                static::compressData(file_get_contents($filename))) !== false)
            return 1;
    }

    /**
     * @param $data
     *
     * @return mixed
     * @throws \wapmorgan\UnifiedArchive\UnsupportedOperationException
     */
    protected static function compressData($data)
    {
        throw new UnsupportedOperationException();
    }
}