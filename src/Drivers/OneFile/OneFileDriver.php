<?php
namespace wapmorgan\UnifiedArchive\Drivers\OneFile;

use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicExtensionDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\EmptyFileListException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

abstract class OneFileDriver extends BasicExtensionDriver
{
    /** @var null|string Should be filled for real format like 'gz' or other */
    const FORMAT = null;

    protected $fileName;
    protected $inArchiveFileName;
    protected $uncompressedSize;
    protected $modificationTime;

    public static function getSupportedFormats()
    {
        return [static::FORMAT];
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
            case static::FORMAT:
                return [BasicDriver::OPEN, BasicDriver::EXTRACT_CONTENT, BasicDriver::STREAM_CONTENT, BasicDriver::CREATE];
        }
    }

    /**
     * @inheritDoc
     * @throws UnsupportedOperationException
     * @throws \Exception
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        $suffix = Formats::getFormatExtension(static::FORMAT);
        if ($suffix === null) {
            throw new \Exception('Format suffix is empty for ' . static::FORMAT . ', it should be initialized!');
        }
        if ($password !== null) {
            throw new UnsupportedOperationException($suffix . ' archive does not support password!');
        }

        parent::__construct($archiveFileName, $format);
        $this->inArchiveFileName = basename($archiveFileName, '.' . $suffix);
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
     * @param string $archiveFileName
     * @param int $archiveFormat
     * @param int $compressionLevel
     * @param string|null $password
     * @param callable|null $fileProgressCallable
     * @return int Number of archived files
     * @throws ArchiveCreationException
     * @throws UnsupportedOperationException
     */
    public static function createArchive(
        array $files,
        $archiveFileName,
        $archiveFormat,
        $compressionLevel = self::COMPRESSION_AVERAGE,
        $password = null,
        $fileProgressCallable = null
    ) {
        if (count($files) > 1) {
            throw new UnsupportedOperationException('One-file format ('.__CLASS__.') could not archive few files');
        }
        if (empty($files)) {
            throw new EmptyFileListException();
        }
        if ($password !== null) {
            throw new UnsupportedOperationException('One-file format ('.__CLASS__.') could not encrypt an archive');
        }

        $filename = array_shift($files);

        $compressed_content = static::compressData(file_get_contents($filename), $compressionLevel);
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
     * @param string $data
     * @param int $compressionLevel
     * @return mixed
     * @throws UnsupportedOperationException
     */
    abstract protected static function compressData($data, $compressionLevel);
}
