<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use CabArchive;
use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicPureDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

class Cab extends BasicPureDriver
{
    const PACKAGE_NAME = 'wapmorgan/cab-archive';
    const MAIN_CLASS = '\CabArchive';

    /** @var CabArchive */
    protected $cab;

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'php-library';
    }

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
     * @return array
     */
    public static function checkFormatSupport($format)
    {
        if (!class_exists('\CabArchive')) {
            return [];
        }

        switch ($format) {
            case Formats::CAB:
                $abilities = [
                    BasicDriver::OPEN,
                ];

                $parts = explode('.', PHP_VERSION);
                // not supported on versions below 7.0.22, 7.1.8, 7.2.0
                if ($parts[0] > 7 || $parts[1] >= 2 || (($parts[1] == 1 && $parts[2] >= 8) || ($parts[1] == 0 && $parts[2] >= 22))) {
                    $abilities[] = BasicDriver::EXTRACT_CONTENT;
                }

                return $abilities;
        }
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        parent::__construct($archiveFileName, $format);
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
    public function getFileStream($fileName)
    {
        return self::wrapStringInStream($this->cab->getFileContent($fileName));
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
}
