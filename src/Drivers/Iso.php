<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicPureDriver;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

class Iso extends BasicPureDriver
{
    const PACKAGE_NAME = 'phpclasses/php-iso-file';
    const MAIN_CLASS = '\CISOFile';

    /** @var \CISOFile */
    protected $iso;

    /** @var array List of files */
    protected $files = [];

    /** @var array  */
    protected $filesData = [];

    /** @var int */
    protected $filesSize = 0;

    /** @var null|int Size of block in ISO. Used to find real position of file in ISO */
    protected $blockSize;

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'iso archives reader';
    }

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::ISO,
        ];
    }

    /**
     * @param $format
     * @return array
     */
    public static function checkFormatSupport($format)
    {
        if (!static::isInstalled()) {
            return [];
        }

        switch ($format) {
            case Formats::ISO:
                return [
                    BasicDriver::OPEN,
                    BasicDriver::EXTRACT_CONTENT,
                ];
        }
    }

    /**
     * @inheritDoc
     * @throws UnsupportedOperationException
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        parent::__construct($archiveFileName, $format);
        $this->open($archiveFileName);
        if ($password !== null)
            throw new UnsupportedOperationException('Iso archive does not support password!');
    }

    /**
     * Iso format destructor
     */
    public function __destruct()
    {
        $this->iso->close();
    }

    /**
     * @param $archiveFileName
     */
    protected function open($archiveFileName)
    {
        // load php-iso-files
        $this->iso = new \CISOFile;
        $this->iso->open($archiveFileName);
        $this->iso->ISOInit();

        /** @var \CVolumeDescriptor $usedDesc */
        $usedDesc = $this->iso->GetDescriptor(SUPPLEMENTARY_VOLUME_DESC);
        if (!$usedDesc)
            $usedDesc = $this->iso->GetDescriptor(PRIMARY_VOLUME_DESC);
        $this->blockSize = $usedDesc->iBlockSize;
        $directories = $usedDesc->LoadMPathTable($this->iso);
        // iterate over all directories
        /** @var \CPathTableRecord $Directory */
        foreach ($directories as $Directory) {
            $directory = $Directory->GetFullPath($directories);
            // ? here is for some unexpected problem with ? appearing
            $directory = trim($directory, '/?');
            if ($directory != '') {
                $directory .= '/';
//                $this->files[$Directory->Location] = $directory;
            }
//            $this->isoCatalogsStructure[$Directory->Location]
//                = $directory;

            /** @var \CFileDirDescriptors[] $files */
            $files = $Directory->LoadExtents($this->iso,
                $usedDesc->iBlockSize, true);
            if ($files) {
                /** @var \CFileDirDescriptors $file */
                foreach ($files as $file) {
                    if (in_array($file->strd_FileId, ['.', '..']) || $file->IsDirectory())
                        continue;
                    $this->files[$file->Location] = $directory.$file->strd_FileId;
                    $this->filesSize += $file->DataLen;

                    $this->filesData[$directory . $file->strd_FileId] =
                        [
                            'size' => $file->DataLen,
                            'mtime' =>
                                strtotime((string)$file->isoRecDate),
                        ];
                }
            }
            // break;
        }
    }

    /**
     * @return ArchiveInformation
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        $information->files = array_values($this->files);
        $information->compressedFilesSize = $information->uncompressedFilesSize = $this->filesSize;
        return $information;
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        return array_values($this->files);
    }

    /**
     * @param string $fileName
     *
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return array_key_exists($fileName, $this->filesData);
    }

    /**
     * @param string $fileName
     *
     * @return ArchiveEntry|false
     */
    public function getFileData($fileName)
    {
        if (!isset($this->filesData[$fileName]))
            return false;

        return new ArchiveEntry(
            $fileName,
            $this->filesData[$fileName]['size'],
            $this->filesData[$fileName]['size'],
            $this->filesData[$fileName]['mtime'],
            false
        );
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     */
    public function getFileContent($fileName)
    {
        $data = $this->prepareForFileExtracting($fileName);
        return $this->iso->Read($data['size']);
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileStream($fileName)
    {
        $data = $this->prepareForFileExtracting($fileName);
        return self::wrapStringInStream($this->iso->Read($data['size']));
    }

    /**
     * @param string $fileName
     * @return array
     */
    protected function prepareForFileExtracting($fileName)
    {
        $Location = array_search($fileName, $this->files, true);
        if (!isset($this->filesData[$fileName])) return false;
        $data = $this->filesData[$fileName];
        $Location_Real = $Location * $this->blockSize;
        if ($this->iso->Seek($Location_Real, SEEK_SET) === false)
            return false;
        return $data;
    }

    /**
     * @param string $outputFolder
     * @param array $files
     * @return int
     * @throws UnsupportedOperationException
     */
    public function extractFiles($outputFolder, array $files)
    {
        foreach ($files as $file) {
            $destination_file = rtrim($outputFolder, '/'). '/' . ltrim($file, '/');
            $destination_dir = dirname($destination_file);

            if (!empty($destination_dir)) {
                if (!is_dir($destination_dir)) {
                    mkdir($destination_dir, 0777, true);
                } else {
                    if (!is_writable($destination_dir)) {
                        chmod($destination_dir, 0777);
                    }
                }
            }

            file_put_contents($destination_file, $this->getFileContent($file));
        }
        return count($files);
    }

    /**
     * @param string $outputFolder
     * @return int
     * @throws UnsupportedOperationException
     * @todo Implement extracting with reading & writing to FS
     */
    public function extractArchive($outputFolder)
    {
        return $this->extractFiles($outputFolder, $this->files);
    }
}
