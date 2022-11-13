<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use Alchemy\Zippy\Archive\Member;
use Alchemy\Zippy\Exception\NoAdapterOnPlatformException;
use Alchemy\Zippy\Zippy;
use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicUtilityDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

class AlchemyZippy extends BasicUtilityDriver
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
     * @var Member[]
     */
    protected $members;

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'php-library and console programs';
    }

    public static function isInstalled()
    {
        self::init();
        return static::$zippy !== false;
    }

    /**
     * @inheritDoc
     */
    public static function getInstallationInstruction()
    {
        self::init();
        return 'install library [alchemy/zippy]: `composer require alchemy/zippy`' . "\n"  . ' and console programs (tar, zip): `apt install tar zip` - depends on OS'
            . "\n" . 'If you install SevenZip and AlchemyZippy:' . "\n" .
            '1. You should specify symfony/console version before installation to any **3.x.x version**:' . "\n" . '`composer require symfony/process:~3.4`, because they require different `symfony/process` versions.' . "\n" .
            '2. Install archive7z version 4.0.0: `composer require gemorroj/archive7z:~4.0`';
    }

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
     * @return array
     */
    public static function checkFormatSupport($format)
    {
        static::init();

        if (static::$zippy === false)
            return [];

        switch ($format) {
            case Formats::TAR_BZIP:
            case Formats::TAR:
            case Formats::TAR_GZIP:
            case Formats::ZIP:
                if (static::checkAdapterFor($format) === false) {
                    return [];
                }

                return [
                    BasicDriver::OPEN,
                    BasicDriver::EXTRACT_CONTENT,
                    BasicDriver::APPEND,
                    BasicDriver::DELETE,
                    BasicDriver::CREATE,
                ];
        }
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
     * @param array $files
     * @param string $archiveFileName
     * @param int $archiveFormat
     * @param int $compressionLevel
     * @param null $password
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
        $fileProgressCallable = null
    )
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
        parent::__construct($archiveFileName, $format);
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
