<?php

namespace wapmorgan\UnifiedArchive\Drivers;

use PhpZip\ZipFile;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\NonExistentArchiveFileException;
use wapmorgan\UnifiedArchive\Formats;

class NelexaZip extends BasicDriver
{
    /**
     * @var ZipFile
     */
    protected $zip;

    /**
     * @var array
     */
    protected $files;

    /**
     * @inheritDoc
     * @throws \PhpZip\Exception\ZipException
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        $this->zip = new ZipFile();
        $this->zip->openFile($archiveFileName);
        if ($password !== null) {
            $this->zip->setReadPassword($password);
        }
    }

    /**
     * @inheritDoc
     */
    public function getArchiveInformation()
    {
        $this->files = [];
        $information = new ArchiveInformation();

        foreach ($this->zip->getAllInfo() as $info) {
            if ($info->isFolder())
                continue;

            $this->files[] = $information->files[] = str_replace('\\', '/', $info->getName());
            $information->compressedFilesSize += $info->getCompressedSize();
            $information->uncompressedFilesSize += $info->getSize();
        }
        return $information;
    }

    /**
     * @inheritDoc
     */
    public function getFileNames()
    {
        return $this->files;
    }

    /**
     * @inheritDoc
     */
    public function isFileExists($fileName)
    {
        return $this->zip->hasEntry($fileName);
    }

    /**
     * @inheritDoc
     */
    public function getFileData($fileName)
    {
        $info = $this->zip->getEntryInfo($fileName);
        return new ArchiveEntry(
            $fileName,
            $info->getCompressedSize(),
            $info->getSize(),
            $info->getMtime(),
            null,
            $info->getComment(),
            $info->getCrc()
        );
    }

    /**
     * @inheritDoc
     */
    public function getFileContent($fileName)
    {
        return $this->zip->getEntryContents($fileName);
    }

    /**
     * @inheritDoc
     * @throws NonExistentArchiveFileException
     */
    public function getFileStream($fileName)
    {
        return static::wrapStringInStream($this->getFileContent($fileName));
    }

    /**
     * @inheritDoc
     */
    public function extractFiles($outputFolder, array $files)
    {
        // TODO: Implement extractFiles() method.
    }

    /**
     * @inheritDoc
     */
    public function extractArchive($outputFolder)
    {
        // TODO: Implement extractArchive() method.
    }

    /**
     * @inheritDoc
     * @throws \PhpZip\Exception\ZipException
     */
    public function addFileFromString($inArchiveName, $content)
    {
        return $this->zip->addFromString($inArchiveName, $content);
    }

    public function getComment()
    {
        return $this->zip->getArchiveComment();
    }

    public function setComment($comment)
    {
        return $this->zip->setArchiveComment($comment);
    }

    public static function getSupportedFormats()
    {
        return [
            Formats::ZIP,
        ];
    }

    public static function checkFormatSupport($format)
    {
        return [
            BasicDriver::OPEN,
            BasicDriver::OPEN_ENCRYPTED,
            BasicDriver::EXTRACT_CONTENT,
            BasicDriver::APPEND,
            BasicDriver::DELETE,
        ];
    }
}