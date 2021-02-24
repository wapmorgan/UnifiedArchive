<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

class Rar extends BasicDriver
{
    const NONE_RAR_COMPRESSION = 48;

    /** @var \RarArchive */
    protected $rar;

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::RAR,
        ];
    }

    /**
     * @param $format
     * @return bool
     */
    public static function checkFormatSupport($format)
    {
        switch ($format) {
            case Formats::RAR:
                return extension_loaded('rar');
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'adapter for ext-rar';
    }

    /**
     * @inheritDoc
     */
    public static function getInstallationInstruction()
    {
        return !extension_loaded('rar')
            ? 'install `rar` extension'
            : null;
    }

    /**
     * @inheritDoc
     */
    public static function canStream($format)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        \RarException::setUsingExceptions(true);
        $this->open($archiveFileName, $password);
    }

    /**
     * @param $archiveFileName
     * @param $password
     * @throws Exception
     */
    protected function open($archiveFileName, $password)
    {
        $this->rar = \RarArchive::open($archiveFileName, $password);
        if ($this->rar === false) {
            throw new Exception('Could not open Rar archive');
        }
    }

    /**
     * Rar format destructor
     */
    public function __destruct()
    {
        $this->rar->close();
    }

    /**
     * @return ArchiveInformation
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        foreach ($this->rar->getEntries() as $i => $entry) {
            if ($entry->isDirectory()) continue;
            $information->files[] = $entry->getName();
            $information->compressedFilesSize += $entry->getPackedSize();
            $information->uncompressedFilesSize += $entry->getUnpackedSize();
        }
        return $information;
    }

    /**
     * @return string|null
     */
    public function getComment()
    {
        return $this->rar->getComment();
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        $files = [];
        foreach ($this->rar->getEntries() as $i => $entry) {
            if ($entry->isDirectory()) continue;
            $files[] = $entry->getName();
        }
        return $files;
    }

    /**
     * @param string $fileName
     *
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return $this->rar->getEntry($fileName) !== false;
    }

    /**
     * @param string $fileName
     *
     * @return ArchiveEntry|false
     */
    public function getFileData($fileName)
    {
        $entry = $this->rar->getEntry($fileName);
        return new ArchiveEntry($fileName, $entry->getPackedSize(), $entry->getUnpackedSize(),
            strtotime($entry->getFileTime()), $entry->getMethod() != self::NONE_RAR_COMPRESSION);
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     */
    public function getFileContent($fileName)
    {
        $entry = $this->rar->getEntry($fileName);
        if ($entry->isDirectory()) return false;
        return stream_get_contents($entry->getStream());
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileStream($fileName)
    {
        $entry = $this->rar->getEntry($fileName);
        if ($entry->isDirectory()) return false;
        return $entry->getStream();
    }

    /**
     * @param string $outputFolder
     * @param array  $files
     *
     * @return false|int
     */
    public function extractFiles($outputFolder, array $files)
    {
        $count = 0;
        foreach ($files as $file) {
            if ($this->rar->getEntry($file)->extract($outputFolder)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param string $outputFolder
     *
     * @return false|resource
     */
    public function extractArchive($outputFolder)
    {
        return $this->extractFiles($outputFolder, $this->getFileNames());
    }

    /**
     * @param string $inArchiveName
     * @param string $content
     * @return bool|void
     * @throws UnsupportedOperationException
     */
    public function addFileFromString($inArchiveName, $content)
    {
        throw new UnsupportedOperationException();
    }
}