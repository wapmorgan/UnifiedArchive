<?php

namespace wapmorgan\UnifiedArchive\Drivers;

use PhpZip\ZipFile;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Commands\BaseArchiveCommand;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicPureDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\NonExistentArchiveFileException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

class NelexaZip extends BasicPureDriver
{
    const PACKAGE_NAME = 'nelexa/zip';
    const MAIN_CLASS = '\\PhpZip\\ZipFile';

    /**
     * @var ZipFile
     */
    protected $zip;

    /**
     * @var array
     */
    protected $files;

    public static function getDescription()
    {
        return 'nelexa/zip driver';
    }

    public static function getSupportedFormats()
    {
        return [
            Formats::ZIP,
        ];
    }

    public static function checkFormatSupport($format)
    {
        if (!static::isInstalled()) {
            return [];
        }
        return [
            BasicDriver::OPEN,
            BasicDriver::OPEN_ENCRYPTED,
            BasicDriver::GET_COMMENT,
            BasicDriver::SET_COMMENT,
            BasicDriver::EXTRACT_CONTENT,
            BasicDriver::APPEND,
            BasicDriver::DELETE,
            BasicDriver::CREATE,
            BasicDriver::CREATE_ENCRYPTED,
            BasicDriver::CREATE_IN_STRING,
        ];
    }

    /**
     * @param array $files
     * @param $archiveFileName
     * @param $archiveFormat
     * @param $compressionLevel
     * @param $password
     * @param $fileProgressCallable
     * @return int
     * @throws ArchiveCreationException
     * @throws UnsupportedOperationException
     */
    public static function createArchive(
        array $files,
        $archiveFileName,
        $archiveFormat,
        $compressionLevel = self::COMPRESSION_AVERAGE,
        $password = null,
        $fileProgressCallable = null)
    {
        if ($fileProgressCallable !== null && !is_callable($fileProgressCallable)) {
            throw new ArchiveCreationException('File progress callable is not callable');
        }

        try {
            $zipFile = static::createArchiveInternal($files, $password, $fileProgressCallable);
            $zipFile->saveAsFile($archiveFileName)->close();
        } catch (\Exception $e) {
            throw new ArchiveCreationException('Could not create archive: '.$e->getMessage(), $e->getCode(), $e);
        }
        return count($files);
    }

    /**
     * @param array $files
     * @param string $archiveFormat
     * @param int $compressionLevel
     * @param string $password
     * @param callable|null $fileProgressCallable
     * @return string Content of archive
     * @throws ArchiveCreationException
     */
    public static function createArchiveInString(
        array $files,
        $archiveFormat,
        $compressionLevel = self::COMPRESSION_AVERAGE,
        $password = null,
        $fileProgressCallable = null
    ) {
        if ($fileProgressCallable !== null && !is_callable($fileProgressCallable)) {
            throw new ArchiveCreationException('File progress callable is not callable');
        }

        try {
            $zipFile = static::createArchiveInternal($files, $password, $fileProgressCallable);
            return $zipFile->outputAsString();
        } catch (\Exception $e) {
            throw new ArchiveCreationException('Could not create archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    protected static function createArchiveInternal(array $files, $password, $fileProgressCallable = null)
    {
        $current_file = 0;
        $total_files = count($files);

        $zipFile = new \PhpZip\ZipFile();
        foreach ($files as $localName => $archiveName) {
            $zipFile->addFile($localName, $archiveName);
            if ($fileProgressCallable !== null) {
                call_user_func_array(
                    $fileProgressCallable,
                    [$current_file++, $total_files, $localName, $archiveName]
                );
            }
        }
        if ($password !== null) {
            $zipFile->setPassword($password);
        }
        return $zipFile;
    }

    /**
     * @inheritDoc
     * @throws \PhpZip\Exception\ZipException
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        parent::__construct($archiveFileName, $format);
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

        $files = method_exists($this->zip, 'getAllInfo')
            ? $this->zip->getAllInfo()
            : $this->zip->getEntries();

        foreach ($files as $info) {
            if (method_exists($info, 'isFolder') ? $info->isFolder() : $info->isDirectory())
                continue;

            $this->files[] = $information->files[] = str_replace('\\', '/', $info->getName());
            $information->compressedFilesSize += $info->getCompressedSize();
            $information->uncompressedFilesSize += method_exists($info, 'getSize') ? $info->getSize() : $info->getUncompressedSize();
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
        $info = method_exists($this->zip, 'getEntryInfo')
            ? $this->zip->getEntryInfo($fileName)
            : $this->zip->getEntry($fileName);

        return new ArchiveEntry(
            $fileName,
            $info->getCompressedSize(),
            method_exists($info, 'getSize') ? $info->getSize() : $info->getUncompressedSize(),
            $info->getMtime()->getTimestamp(),
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
        $this->zip->extractTo($outputFolder, $files);
        return count($files);
    }

    /**
     * @inheritDoc
     */
    public function extractArchive($outputFolder)
    {
        $this->zip->extractTo($outputFolder);
        return count($this->files);
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

    public function deleteFiles(array $files)
    {
        $deleted = 0;
        foreach ($files as $file) {
            $this->zip->deleteFromName($file);
            $deleted++;
        }
        return $deleted;
    }

    public function addFiles(array $files)
    {
        foreach ($files as $inArchiveName => $fsFileName)
        {
            $this->zip->addFile($fsFileName, $inArchiveName);
        }
    }
}
