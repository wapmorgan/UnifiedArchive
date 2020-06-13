<?php
namespace wapmorgan\UnifiedArchive\Formats;

use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;

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

    /** @var null|int Size of block in ISO. Used to find real position of file in ISO */
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
            $directory = trim($directory, '/');
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
        $data = $this->prepareForFileExtracting($fileName);
        return $this->iso->Read($data['size']);
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileResource($fileName)
    {
        $data = $this->prepareForFileExtracting($fileName);
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $this->iso->Read($data['size']));
        rewind($resource);
        return $resource;
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
     * @return void
     * @throws UnsupportedOperationException
     * @todo Implement extracting with reading & writing to FS
     */
    public function extractFiles($outputFolder, array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param string $outputFolder
     * @return void
     * @throws UnsupportedOperationException
     * @todo Implement extracting with reading & writing to FS
     */
    public function extractArchive($outputFolder)
    {
        throw new UnsupportedOperationException();
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
     * @return void
     * @throws UnsupportedOperationException
     */
    public static function createArchive(array $files, $archiveFileName){
        throw new UnsupportedOperationException();
    }
}