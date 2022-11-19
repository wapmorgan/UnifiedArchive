<?php

namespace wapmorgan\UnifiedArchive\Drivers;

use splitbrain\PHPArchive\ArchiveIllegalCompressionException;
use splitbrain\PHPArchive\ArchiveIOException;
use splitbrain\PHPArchive\FileInfo;
use splitbrain\PHPArchive\Tar;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicPureDriver;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

class SplitbrainPhpArchive extends BasicPureDriver
{
    const PACKAGE_NAME = 'splitbrain/php-archive';
    const MAIN_CLASS = '\\splitbrain\\PHPArchive\\Archive';

    /**
     * @var \splitbrain\PHPArchive\Zip|Tar
     */
    protected $archive;
    /**
     * @var array
     */
    protected $files;

    /** @var FileInfo[] */
    protected $members;

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'php-library for zip/tar (with gzip/bzip-compression)';
    }

    /**
     * @inheritDoc
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::ZIP,
            Formats::TAR,
            Formats::TAR_GZIP,
            Formats::TAR_BZIP,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function checkFormatSupport($format)
    {
        if (!static::isInstalled()) {
            return [];
        }

        if (
            ($format === Formats::TAR_BZIP && !extension_loaded('bzip2'))
            || ($format === Formats::TAR_GZIP && !extension_loaded('zlib'))
        ) {
            return [];
        }

        switch ($format) {
            case Formats::ZIP:
            case Formats::TAR:
            case Formats::TAR_GZIP;
            case Formats::TAR_BZIP;
                return [
                    BasicDriver::OPEN,
//                    BasicDriver::EXTRACT_CONTENT,
                    BasicDriver::APPEND,
                    BasicDriver::CREATE,
                    BasicDriver::CREATE_ENCRYPTED,
                    BasicDriver::CREATE_IN_STRING,
                ];
        }
    }

    /**
     * @throws ArchiveIllegalCompressionException
     * @throws ArchiveIOException
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        parent::__construct($archiveFileName, $format, $password);
        if ($format === Formats::ZIP) {
            $this->archive = new \splitbrain\PHPArchive\Zip();
        } else {
            $this->archive = new Tar();
        }
        $this->archive->open($archiveFileName);
    }

    /**
     * @inheritDoc
     */
    public function getArchiveInformation()
    {
        $this->files = [];
        $information = new ArchiveInformation();

        foreach ($this->archive->contents() as $member) {
            if ($member->getIsdir()) {
                continue;
            }

            $this->files[] = $information->files[] = str_replace('\\', '/', $member->getPath());
            $this->members[str_replace('\\', '/', $member->getPath())] = $member;
            $information->compressedFilesSize += (int)$member->getCompressedSize();
            $information->uncompressedFilesSize += (int)$member->getSize();
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
        return array_key_exists($fileName, $this->members);
    }

    /**
     * @inheritDoc
     */
    public function getFileData($fileName)
    {
        $entry = $this->members[$fileName];
        return new ArchiveEntry(
            $fileName,
            $entry->getCompressedSize(),
            $entry->getSize(),
            strtotime($entry->getMtime()),
            $entry->getSize() !== $entry->getCompressedSize(),
            $entry->getComment(),
            null
        );
    }

    /**
     * @inheritDoc
     * @throws UnsupportedOperationException
     */
    public function getFileContent($fileName)
    {
        throw new UnsupportedOperationException('Getting file content is not supported by ' . __CLASS__);
    }

    /**
     * @inheritDoc
     * @throws UnsupportedOperationException
     */
    public function getFileStream($fileName)
    {
        throw new UnsupportedOperationException('Getting file stream is not supported by ' . __CLASS__);
    }

    /**
     * @inheritDoc
     * @throws UnsupportedOperationException
     */
    public function extractFiles($outputFolder, array $files)
    {
        throw new UnsupportedOperationException('Extract specific files is not supported by ' . __CLASS__);
    }

    /**
     * @inheritDoc
     */
    public function extractArchive($outputFolder)
    {
        $this->archive->extract($outputFolder);
    }
}
