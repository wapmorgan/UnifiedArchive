<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Alchemy\Zippy\Exception\NoAdapterOnPlatformException;
use Alchemy\Zippy\Zippy;
use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

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
        if (static::$zippy === null)
            static::$zippy = Zippy::load();
    }

    /**
     * @param $format
     */
    public static function checkFormatSupport($format)
    {
        static::init();

        switch ($format) {
            case Formats::TAR_BZIP:
            case Formats::TAR:
            case Formats::TAR_GZIP:
            case Formats::ZIP:
                return static::checkAdapterFor($format);
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
     * @param $format
     * @return bool
     */
    public static function canCreateArchive($format)
    {
        return false;
    }

    /**
     * @param $format
     * @return bool
     */
    public static function canAddFiles($format)
    {
        return false;
    }

    /**
     * @param $format
     * @return bool
     */
    public static function canDeleteFiles($format)
    {
        return false;
    }

    /**
     * @return false
     */
    public static function canUsePassword()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $password = null)
    {
        $this->fileName = $archiveFileName;
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
        foreach ($this->archive->getMembers() as $member) {
            if ($member->isDir())
                continue;
            if ($member->getLocation() === $fileName)
                return $member;
        }
        return null;
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
    public function getFileResource($fileName)
    {
        $member = $this->getMember($fileName);
        return $member->getResource();
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
}