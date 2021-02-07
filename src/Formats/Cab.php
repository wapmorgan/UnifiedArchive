<?php
namespace wapmorgan\UnifiedArchive\Formats;

use CabArchive;
use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

class Cab extends BasicDriver
{
    /** @var CabArchive */
    protected $cab;

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::CAB,
        ];
    }

    /**
     * @param $format
     * @return bool
     */
    public static function checkFormatSupport($format)
    {
        switch ($format) {
            case Formats::CAB:
                return class_exists('\CabArchive');
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'php-library';
    }

    /**
     * @inheritDoc
     */
    public static function getInstallationInstruction()
    {
        return 'install library `wapmorgan/cab-archive`';
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        if ($password !== null)
            throw new UnsupportedOperationException('Cab archive does not support password!');
        $this->open($archiveFileName);
    }

    /**
     * Iso format destructor
     */
    public function __destruct()
    {
        $this->cab = null;
    }

    /**
     * @param $archiveFileName
     * @throws Exception
     */
    protected function open($archiveFileName)
    {
        try {
            $this->cab = new CabArchive($archiveFileName);
        } catch (Exception $e) {
            throw new Exception('Could not open Cab archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return ArchiveInformation
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        foreach ($this->cab->getFileNames() as $file) {
            $information->files[] = $file;
            $file_info = $this->cab->getFileData($file);
            $information->uncompressedFilesSize += $file_info->size;
            $information->compressedFilesSize += $file_info->packedSize;
        }
        return $information;
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        return $this->cab->getFileNames();
    }

    /**
     * @param string $fileName
     *
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return in_array($fileName, $this->cab->getFileNames(), true);
    }

    /**
     * @param string $fileName
     *
     * @return ArchiveEntry|false
     */
    public function getFileData($fileName)
    {
        $data = $this->cab->getFileData($fileName);

        return new ArchiveEntry($fileName, $data->packedSize, $data->size, $data->unixtime, $data->is_compressed);
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     * @throws Exception
     */
    public function getFileContent($fileName)
    {
        return $this->cab->getFileContent($fileName);
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     * @throws Exception
     */
    public function getFileResource($fileName)
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $this->cab->getFileContent($fileName));
        rewind($resource);
        return $resource;
    }

    /**
     * @param string $outputFolder
     * @param array $files
     * @return int Number of extracted files
     * @throws ArchiveExtractionException
     */
    public function extractFiles($outputFolder, array $files)
    {
        try {
            return $this->cab->extract($outputFolder, $files);
        } catch (Exception $e) {
            throw new ArchiveExtractionException($e->getMessage(),
                $e->getCode(),
                $e->getPrevious()
            );
        }
    }

    /**
     * @param string $outputFolder
     * @return int
     * @throws ArchiveExtractionException
     */
    public function extractArchive($outputFolder)
    {
        try {
            return $this->cab->extract($outputFolder);
        } catch (Exception $e) {
            throw new ArchiveExtractionException($e->getMessage(),
                $e->getCode(),
                $e->getPrevious()
            );
        }
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
     * @param string $inArchiveName
     * @param string $content
     * @return bool|void
     * @throws UnsupportedOperationException
     */
    public function addFileFromString($inArchiveName, $content)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param array $files
     * @param string $archiveFileName
     * @param int $compressionLevel
     * @return void
     * @throws UnsupportedOperationException
     */
    public static function createArchive(array $files, $archiveFileName, $compressionLevel = self::COMPRESSION_AVERAGE)
    {
        throw new UnsupportedOperationException();
    }
}