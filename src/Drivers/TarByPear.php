<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use Archive_Tar;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\NonExistentArchiveFileException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\LzwStreamWrapper;

class TarByPear extends BasicDriver
{
    /**
     * @var string Full path to archive
     */
    protected $archiveFileName;

    /**
     * @var Archive_Tar
     */
    protected $tar;

    /**
     * @var float Overall compression ratio of Tar archive when Archive_Tar is used
     */
    protected $pearCompressionRatio;

    /**
     * @var array<string, integer> List of files and their index in listContent() result
     */
    protected $pearFilesIndex;

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::TAR,
            Formats::TAR_GZIP,
            Formats::TAR_BZIP,
            Formats::TAR_LZMA,
            Formats::TAR_LZW,
        ];
    }

    /**
     * @param $format
     * @return bool
     * @throws \Exception
     */
    public static function checkFormatSupport($format)
    {
        $availability = class_exists('\Archive_Tar');
        if (!$availability) return false;
        switch ($format) {
            case Formats::TAR:
                return true;

            case Formats::TAR_GZIP:
                return extension_loaded('zlib');

            case Formats::TAR_BZIP:
                return extension_loaded('bz2');

            case Formats::TAR_LZMA:
                return extension_loaded('xz');

            case Formats::TAR_LZW:
                return LzwStreamWrapper::isBinaryAvailable();
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'php-library for tar';
    }

    /**
     * @inheritDoc
     */
    public static function getInstallationInstruction()
    {
        return !class_exists('\Archive_Tar')
            ? 'install library `pear/archive_tar` and optionally php-extensions (zlib, bzip2)'
            : null;
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
            throw new UnsupportedOperationException('One-file format ('.__CLASS__.') could not encrypt an archive');
        }

        $compression = null;
        switch (strtolower(pathinfo($archiveFileName, PATHINFO_EXTENSION))) {
            case 'gz':
            case 'tgz':
                $compression = 'gz';
                break;
            case 'bz2':
            case 'tbz2':
                $compression = 'bz2';
                break;
            case 'xz':
                $compression = 'lzma2';
                break;
            case 'z':
                $tar_aname = 'compress.lzw://' . $archiveFileName;
                break;
        }

        if (isset($tar_aname))
            $tar = new Archive_Tar($tar_aname, $compression);
        else
            $tar = new Archive_Tar($archiveFileName, $compression);

        foreach ($files  as $localName => $filename) {
            $remove_dir = dirname($filename);
            $add_dir = dirname($localName);

            if (is_null($filename)) {
                if ($tar->addString($localName, '') === false)
                    throw new ArchiveCreationException('Error when adding directory '.$localName.' to archive');
            } else {
                if ($tar->addModify($filename, $add_dir, $remove_dir) === false)
                    throw new ArchiveCreationException('Error when adding file '.$filename.' to archive');
            }
        }
        $tar = null;

        return count($files);
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        $this->archiveFileName = realpath($archiveFileName);
        $this->open($format);
    }

    protected function open($archiveType)
    {
        switch ($archiveType) {
            case Formats::TAR_GZIP:
                $this->tar = new Archive_Tar($this->archiveFileName, 'gz');
                break;

            case Formats::TAR_BZIP:
                $this->tar = new Archive_Tar($this->archiveFileName, 'bz2');
                break;

            case Formats::TAR_LZMA:
                $this->tar = new Archive_Tar($this->archiveFileName, 'lzma2');
                break;

            case Formats::TAR_LZW:
                LzwStreamWrapper::registerWrapper();
                $this->tar = new Archive_Tar('compress.lzw://' . $this->archiveFileName);
                break;

            default:
                $this->tar = new Archive_Tar($this->archiveFileName);
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        $this->pearFilesIndex = [];

        foreach ($this->tar->listContent() as $i => $file) {
            // BUG workaround: http://pear.php.net/bugs/bug.php?id=20275
            if ($file['filename'] === 'pax_global_header') {
                continue;
            }
            // skip directories
            if ($file['typeflag'] == '5' || substr($file['filename'], -1) === '/')
                continue;
            $information->files[] = $file['filename'];
            $information->uncompressedFilesSize += $file['size'];
            $this->pearFilesIndex[$file['filename']] = $i;
        }

        $information->compressedFilesSize = filesize($this->archiveFileName);
        $this->pearCompressionRatio = $information->uncompressedFilesSize != 0
            ? ceil($information->compressedFilesSize / $information->uncompressedFilesSize)
            : 1;
        return $information;
    }

    /**
     * @inheritDoc
     */
    public function getFileNames()
    {
        $files = [];

        $Content = $this->tar->listContent();
        foreach ($Content as $i => $file) {
            // BUG workaround: http://pear.php.net/bugs/bug.php?id=20275
            if ($file['filename'] === 'pax_global_header') {
                continue;
            }
            $files[] = $file['filename'];
        }

        return $files;
    }

    /**
     * @inheritDoc
     */
    public function isFileExists($fileName)
    {
        return isset($this->pearFilesIndex[$fileName]);
    }

    /**
     * @inheritDoc
     */
    public function getFileData($fileName)
    {
        if (!isset($this->pearFilesIndex[$fileName]))
            throw new NonExistentArchiveFileException('File '.$fileName.' is not found in archive files list');

        $index = $this->pearFilesIndex[$fileName];

        $files_list = $this->tar->listContent();
        if (!isset($files_list[$index]))
            throw new NonExistentArchiveFileException('File '.$fileName.' is not found in Tar archive');

        $data = $files_list[$index];
        unset($files_list);

        return new ArchiveEntry($fileName, $data['size'] / $this->pearCompressionRatio,
            $data['size'], $data['mtime'], in_array(strtolower(pathinfo($this->archiveFileName,
                PATHINFO_EXTENSION)), ['gz', 'bz2', 'xz', 'z']));
    }

    /**
     * @inheritDoc
     */
    public function getFileContent($fileName)
    {
        if (!isset($this->pearFilesIndex[$fileName]))
            throw new NonExistentArchiveFileException('File '.$fileName.' is not found in archive files list');

        return $this->tar->extractInString($fileName);
    }

    /**
     * @inheritDoc
     */
    public function getFileStream($fileName)
    {
        if (!isset($this->pearFilesIndex[$fileName]))
            throw new NonExistentArchiveFileException('File '.$fileName.' is not found in archive files list');

        return self::wrapStringInStream($this->tar->extractInString($fileName));
    }

    /**
     * @inheritDoc
     */
    public function extractFiles($outputFolder, array $files)
    {
        $result = $this->tar->extractList($files, $outputFolder);
        if ($result === false) {
            throw new ArchiveExtractionException('Error when extracting from '.$this->archiveFileName);
        }

        return count($files);
    }

    /**
     * @inheritDoc
     */
    public function extractArchive($outputFolder)
    {
        $result = $this->tar->extract($outputFolder);
        if ($result === false) {
            throw new ArchiveExtractionException('Error when extracting from '.$this->archiveFileName);
        }

        return 1;
    }

    /**
     * @inheritDoc
     */
    public function addFiles(array $files)
    {
        $added = 0;
        foreach ($files as $localName => $filename) {
            $remove_dir = dirname($filename);
            $add_dir = dirname($localName);
            if (is_null($filename)) {
                if ($this->tar->addString($localName, "") === false) {
                    throw new ArchiveModificationException('Could not add directory "'.$filename.'": '.$this->tar->error_object->message, $this->tar->error_object->code);
                }
            } else {
                if ($this->tar->addModify($filename, $add_dir, $remove_dir) === false) {
                    throw new ArchiveModificationException('Could not add file "'.$filename.'": '.$this->tar->error_object->message, $this->tar->error_object->code);
                }
                $added++;
            }
        }
        return $added;
    }

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
     * @param string $inArchiveName
     * @param string $content
     * @return bool|true
     */
    public function addFileFromString($inArchiveName, $content)
    {
        return $this->tar->addString($inArchiveName, $content);
    }
}