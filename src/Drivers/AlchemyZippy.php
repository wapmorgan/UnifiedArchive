<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use Alchemy\Zippy\Archive\Member;
use Alchemy\Zippy\Exception\NoAdapterOnPlatformException;
use Alchemy\Zippy\Zippy;
use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;

class AlchemyZippy extends BasicDriver
{
    /**
     * @var Zippy
     */
    protected static $zippy;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var \Alchemy\Zippy\Archive\ArchiveInterface
     */
    protected $archive;

    /**
     * @var array
     */
    protected $files;

    /**
     * @var string
     */
    protected $format;

    /**
     * @var Member[]
     */
    protected $members;

    /**
     * @return mixed|void
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

    protected static function init()
    {
        if (!class_exists('\Alchemy\Zippy\Zippy'))
            static::$zippy = false;
        else if (static::$zippy === null)
            static::$zippy = Zippy::load();
    }

    /**
     * @param $format
     * @return bool
     */
    public static function checkFormatSupport($format)
    {
        static::init();

        if (static::$zippy === false)
            return false;

        switch ($format) {
            case Formats::TAR_BZIP:
            case Formats::TAR:
            case Formats::TAR_GZIP:
            case Formats::ZIP:
                return static::checkAdapterFor($format);
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'php-library and console programs';
    }

    /**
     * @inheritDoc
     */
    public static function getInstallationInstruction()
    {
        self::init();
        return static::$zippy === false
            ? 'install library `alchemy/zippy` and console programs (tar, zip)'
            : null;
    }

    /**
     * @param $format
     * @param $adapter
     * @return bool
     */
    protected static function checkAdapterFor($format, &$adapter = null)
    {
        try {
            $adapter = static::$zippy->getAdapterFor($format);
            return true;
        } catch (NoAdapterOnPlatformException $e) {
            return false;
        }
    }

    /**
     * @param $format
     * @return bool
     */
    public static function canCreateArchive($format)
    {
        return true;
    }

    /**
     * @param $format
     * @return bool
     */
    public static function canAddFiles($format)
    {
        return true;
    }

    /**
     * @param $format
     * @return bool
     */
    public static function canDeleteFiles($format)
    {
        return true;
    }

    /**
     * @param array $files
     * @param string $archiveFileName
     * @param int $archiveFormat
     * @param int $compressionLevel
     * @param null $password
     * @return int
     * @throws ArchiveCreationException
     * @throws UnsupportedOperationException
     */
    public static function createArchive(array $files, $archiveFileName, $archiveFormat, $compressionLevel = self::COMPRESSION_AVERAGE, $password = null)
    {
        if ($password !== null) {
            throw new UnsupportedOperationException('AlchemyZippy could not encrypt an archive');
        }

        try {
            $archive = static::$zippy->create($archiveFileName, $files);
        } catch (Exception $e) {
            throw new ArchiveCreationException('Could not create archive: '.$e->getMessage(), $e->getCode(), $e);
        }
        return count($files);
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        $this->fileName = $archiveFileName;
        $this->format = $format;
        $this->archive = static::$zippy->open($archiveFileName);
    }

    /**
     * @inheritDoc
     */
    public function getArchiveInformation()
    {
        $this->files = [];
        $information = new ArchiveInformation();

        foreach ($this->archive->getMembers() as $member) {
            if ($member->isDir())
                continue;

            $this->files[] = $information->files[] = str_replace('\\', '/', $member->getLocation());
            $this->members[str_replace('\\', '/', $member->getLocation())] = $member;
            $information->compressedFilesSize += (int)$member->getSize();
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
        return in_array($fileName, $this->files);
    }

    protected function getMember($fileName)
    {
        return $this->members[$fileName];

//        foreach ($this->archive->getMembers() as $member) {
//            if ($member->isDir())
//                continue;
//            if ($member->getLocation() === $fileName)
//                return $member;
//        }
//        return null;
    }

    /**
     * @inheritDoc
     */
    public function getFileData($fileName)
    {
        $member = $this->getMember($fileName);
        return new ArchiveEntry($member->getLocation(), $member->getSize(), $member->getSize(), strtotime($member->getLastModifiedDate()), true);
    }

    /**
     * @inheritDoc
     */
    public function getFileContent($fileName)
    {
        $member = $this->getMember($fileName);
        return (string)$member;
    }

    /**
     * @inheritDoc
     */
    public function getFileStream($fileName)
    {
        $member = $this->getMember($fileName);
        return self::wrapStringInStream((string)$member);
    }

    /**
     * @inheritDoc
     */
    public function extractFiles($outputFolder, array $files)
    {
        try {
            foreach ($files as $file) {
                $member = $this->getMember($file);
                $member->extract($outputFolder);
            }
        } catch (Exception $e) {
            throw new ArchiveExtractionException('Could not extract archive: '.$e->getMessage(), $e->getCode(), $e);
        }
        return count($files);
    }

    /**
     * @inheritDoc
     */
    public function extractArchive($outputFolder)
    {
        try {
            $this->archive->extract($outputFolder);
        } catch (Exception $e) {
            throw new ArchiveExtractionException('Could not extract archive: '.$e->getMessage(), $e->getCode(), $e);
        }
        return count($this->files);
    }

    /**
     * @inheritDoc
     */
    public function deleteFiles(array $files)
    {
        $members = [];
        try {
            foreach ($files as $file) {
                $members[] = $this->getMember($file);
            }
            $this->archive->removeMembers($members);
        } catch (Exception $e) {
            throw new ArchiveModificationException('Could not remove files from archive: '.$e->getMessage(), $e->getCode(), $e);
        }
        return count($files);
    }

    /**
     * @inheritDoc
     */
    public function addFiles(array $files)
    {
        $added = 0;
        try {
            foreach ($files as $localName => $filename) {
                if (is_null($filename)) {
//                    $this->archive->addEmptyDir($localName);
                } else {
                    $this->archive->addMembers([$filename => $localName]);
                    $added++;
                }
            }
        } catch (Exception $e) {
            throw new ArchiveModificationException('Could not add file "'.$filename.'": '.$e->getMessage(), $e->getCode());
        }
        $this->getArchiveInformation();
        return $added;
    }

    /**
     * @param string $inArchiveName
     * @param string $content
     * @return bool
     */
    public function addFileFromString($inArchiveName, $content)
    {
        $fp = fopen('php://memory', 'w');
        fwrite($fp, $content);
        rewind($fp);
        $this->archive->addMembers([$inArchiveName => $fp]);
        fclose($fp);
        return true;
    }
}