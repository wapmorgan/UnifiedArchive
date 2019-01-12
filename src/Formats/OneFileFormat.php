<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\EmptyFileListException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;

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
     * @return ArchiveEntry|false
     */
    public function getFileData($fileName)
    {
        return new ArchiveEntry(
            $this->inArchiveFileName,
            filesize($this->fileName),
            $this->uncompressedSize,
            $this->modificationTime);
    }

    /**
     * @param string $outputFolder
     * @param array $files
     * @return int
     * @throws ArchiveExtractionException
     */
    public function extractFiles($outputFolder, array $files = null)
    {
        return $this->extractArchive($outputFolder);
    }

    /**
     * @param string $outputFolder
     * @return int
     * @throws ArchiveExtractionException
     */
    public function extractArchive($outputFolder)
    {
        $data = $this->getFileContent($this->inArchiveFileName);
        if ($data === false)
            throw new ArchiveExtractionException('Could not extract archive');

        $size = strlen($data);
        $written = file_put_contents($outputFolder.$this->inArchiveFileName, $data);

        if ($written === true) {
            throw new ArchiveExtractionException('Could not extract file "'.$this->inArchiveFileName.'": could not write data');
        } else if ($written < $size) {
            throw new ArchiveExtractionException('Could not archive file "'.$this->inArchiveFileName.'": written '.$written.' of '.$size);
        }
        return 1;
    }

    /**
     * @param array $files
     * @return void
     * @throws UnsupportedOperationException
     */
    public function deleteFiles(array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param array $files
     * @return void
     * @throws UnsupportedOperationException
     */
    public function addFiles(array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param array $files
     * @param string $archiveFileName
     * @return int
     * @throws UnsupportedOperationException
     * @throws EmptyFileListException
     * @throws ArchiveCreationException
     */
    public static function createArchive(array $files, $archiveFileName){
        if (count($files) > 1) {
            throw new UnsupportedOperationException('One-file format ('.__CLASS__.') could not archive few files');
        }
        if (empty($files)) {
            throw new EmptyFileListException();
        }

        $filename = array_shift($files);

        $compressed_content = static::compressData(file_get_contents($filename));
        $size = strlen($compressed_content);
        $written = file_put_contents($archiveFileName, $compressed_content);

        if ($written === true) {
            throw new ArchiveCreationException('Could not archive file: could not write data');
        } else if ($written < $size) {
            throw new ArchiveCreationException('Could not archive file: written '.$written.' of '.$size);
        }
        return 1;
    }

    /**
     * @param $data
     *
     * @return mixed
     * @throws UnsupportedOperationException
     */
    protected static function compressData($data)
    {
        throw new UnsupportedOperationException();
    }
}