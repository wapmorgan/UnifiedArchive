<?php
namespace wapmorgan\UnifiedArchive\Formats;

use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;

class Iso extends BasicFormat
{
    /** @var \CISOFile */
    protected $iso;

    /** @var array List of files */
    protected $files = [];

    /** @var array  */
    protected $filesData = [];

    /** @var int */
    protected $filesSize = 0;

    /** @var null|int */
    protected $blockSize;

    /**
     * BasicFormat constructor.
     *
     * @param string $archiveFileName
     */
    public function __construct($archiveFileName)
    {
        $this->open($archiveFileName);
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

        $usedDesc =
            $this->iso->GetDescriptor(SUPPLEMENTARY_VOLUME_DESC);
        if (!$usedDesc)
            $usedDesc = $this->iso->GetDescriptor(PRIMARY_VOLUME_DESC);
        $this->blockSize = $usedDesc->iBlockSize;

        $directories = $usedDesc->LoadMPathTable($this->iso);
        foreach ($directories as $Directory) {
            $directory = $Directory->GetFullPath($directories, false);
            $directory = trim($directory, '/');
            if ($directory != '') {
                $directory .= '/';
//                $this->files[$Directory->Location] = $directory;
            }
//            $this->isoCatalogsStructure[$Directory->Location]
//                = $directory;

            $files = $Directory->LoadExtents($this->iso,
                $usedDesc->iBlockSize, true);
            if ($files) {
                foreach ($files as $file) {
                    if (in_array($file->strd_FileId, ['.', '..']))
                        continue;
                    $this->files[$file->Location]
                        = $directory . $file->strd_FileId;
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

        return new ArchiveEntry($fileName, $this->filesData[$fileName]['size'],
            $this->filesData[$fileName]['size'], $this->filesData[$fileName]['mtime'],false);
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     */
    public function getFileContent($fileName)
    {
        // TODO: Implement getFileContent() method.
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileResource($fileName)
    {
        // TODO: Implement getFileResource() method.
    }

    /**
     * @param string $outputFolder
     * @param array  $files
     *
     * @return false|resource
     */
    public function extractFiles($outputFolder, array $files)
    {
        // TODO: Implement extractFiles() method.
    }

    /**
     * @param string $outputFolder
     *
     * @return false|resource
     */
    public function extractArchive($outputFolder)
    {
        // TODO: Implement extractArchive() method.
    }

    /**
     * @param array $files
     *
     * @return false|int
     */
    public function deleteFiles(array $files)
    {
        // TODO: Implement deleteFiles() method.
    }

    /**
     * @param array $files
     *
     * @return false|int
     */
    public function addFiles(array $files)
    {
        // TODO: Implement addFiles() method.
    }

    /**
     * @param array  $files
     * @param string $archiveFileName
     *
     * @return false|int
     */
    public static function createArchive(array $files, $archiveFileName){
 // TODO: Implement createArchive() method.
}
}