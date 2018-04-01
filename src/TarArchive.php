<?php
namespace wapmorgan\UnifiedArchive;

use Archive_Tar;
use Exception;
use Phar;
use PharData;
use RecursiveIteratorIterator;

class TarArchive extends AbstractArchive
{
    /** @var string */
    protected $path;

    /** @var Archive_Tar|PharData */
    protected $tar;

    /** @var bool */
    protected $enabledPearTar;

    /** @var bool */
    protected $enabledPharData;

    /** @var int */
    protected $numberOfFiles;

    /** @var array */
    protected $files;

    /** @var int */
    protected $uncompressedFilesSize;

    /** @var int */
    protected $compressedFilesSize;

    /** @var int */
    protected $archiveSize;

    /** @var float */
    protected $compressionRatio;

    /**
     * @param $fileName
     * @return null|TarArchive
     */
    public static function open($fileName)
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (((in_array($ext, array('tar', 'tgz', 'tbz2', 'txz')) || preg_match('~\.tar\.(gz|bz2|xz|Z)$~i', $fileName)) && class_exists('\Archive_Tar'))
            || (in_array($ext, array('tar', 'tgz', 'tbz2')) || preg_match('~\.tar\.(gz|bz2)$~i', $fileName)) && class_exists('\PharData'))
            return new self($fileName);
        return null;
    }

    /**
     * TarArchive constructor.
     * @param $fileName
     * @param null $type
     * @throws Exception
     */
    public function __construct($fileName, $type = null)
    {
        $this->enabledPearTar = class_exists('\Archive_Tar');
        $this->enabledPharData = class_exists('\PharData');
        $this->path = realpath($fileName);

        if (!$this->enabledPharData && !$this->enabledPearTar)
            throw new Exception('Archive_Tar nor PharData not available');

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'gz':
            case 'tgz':
                if ($this->enabledPharData)
                    $this->tar = new PharData($fileName);
                else
                    $this->tar = new Archive_Tar($fileName, 'gz');
                break;
            case 'bz2':
            case 'tbz2':
                if ($this->enabledPharData)
                    $this->tar = new PharData($fileName);
                else
                    $this->tar = new Archive_Tar($fileName, 'bz2');
                break;
            case 'xz':
                if (!$this->enabledPharData)
                    throw new Exception('Archive_Tar not available');
                $this->tar = new Archive_Tar($fileName, 'lzma2');
                break;
            case 'z':
                if (!$this->enabledPharData)
                    throw new Exception('Archive_Tar not available');
                $this->tar = new Archive_Tar('compress.lzw://'.$fileName);
                break;
            default:
                if ($this->enabledPharData)
                    $this->tar = new PharData($fileName);
                else
                    $this->tar = new Archive_Tar($fileName);
                break;
        }

        $this->scanArray();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->tar = null;
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        return $this->files;
    }

    /**
     * @param $fileName
     * @return bool|ArchiveEntry
     */
    public function getFileData($fileName)
    {
        if (!in_array($fileName, $this->files))
            return false;

        if ($this->tar instanceof Archive_Tar) {
            $index = array_search($fileName, $this->files);
            $Content = $this->tar->listContent();
            $data = $Content[$index];
            unset($Content);

            return new ArchiveEntry($fileName, $data['size'] / $this->tarCompressionRatio,
                $data['size'], $data['mtime'], in_array(strtolower(pathinfo($this->tar->path,
                    PATHINFO_EXTENSION)), array('gz', 'bz2', 'xz', 'Z')));
        } else {
            /** @var \PharFileInfo $entry_info */
            $entry_info = $this->tar[$fileName];

            return new ArchiveEntry($fileName, $entry_info->getSize(), filesize($entry_info->getPathname()),
                0, $entry_info->isCompressed());
        }
        return false;
    }

    /**
     * @param $filename
     * @return string
     */
    public function getFileContent($filename)
    {
        if (!in_array($filename, $this->files))
            return false;
        if ($this->tar instanceof Archive_Tar)
            return $this->tar->extractInString($filename);
        else
            return $this->tar[$filename]->getContent();
    }

    /**
     * @return array
     */
    public function getHierarchy()
    {
        $tree = array(DIRECTORY_SEPARATOR);
        foreach ($this->files as $filename) {
            if (in_array(substr($filename, -1), array('/', '\\')))
                $tree[] = DIRECTORY_SEPARATOR.$filename;
        }

        return $tree;
    }

    /**
     * @param $outputFolder
     * @param string|array|null $files
     * @return bool|int
     */
    public function extractFiles($outputFolder, $files = null)
    {
        $list = array();
        if ($files === null) {
            $list = array_values($this->files);
        } else {
            foreach ($this->files as $fname) {
                if (strpos($fname, $files) === 0) {
                    $list[] = $fname;
                }
            }
        }

        if ($this->tar instanceof Archive_Tar) {
            $result = $this->tar->extractList($list, $outputFolder);
        } else {
            $result = $this->tar->extractTo($outputFolder, $list, true);
        }

        if ($result === true) {
            return count($list);
        } else {
            return false;
        }
    }

    /**
     * @param string|array $fileOrFiles
     * @return bool|int
     */
    public function deleteFiles($fileOrFiles)
    {
        if ($this->tar instanceof Archive_Tar)
            return false;

        $files = is_string($fileOrFiles) ? array($fileOrFiles) : $fileOrFiles;
        $deleted = 0;

        foreach ($files as $i => $file) {
            if (!in_array($file, $this->files, true))
                continue;

            if ($this->tar->delete($file))
                $deleted++;
        }

        return $deleted;
    }

    /**
     * @param $fileOrFiles
     * @return int|bool
     */
    public function addFiles($fileOrFiles)
    {
        $fileOrFiles = self::createFilesList($fileOrFiles);

        if ($this->tar instanceof Archive_Tar) {
            foreach ($fileOrFiles as $localname => $filename) {
                $remove_dir = dirname($filename);
                $add_dir = dirname($localname);
                if (is_null($filename)) {
                    if ($this->tar->addString($localname, "") === false)
                        return false;
                } else {
                    if ($this->tar->addModify($filename, $add_dir, $remove_dir) === false)
                        return false;
                }
            }
        } else {
            foreach ($fileOrFiles as $localname => $filename) {
                if (is_null($filename)) {
                    if ($this->tar->addEmptyDir($localname) === false)
                        return false;
                } else {
                    if ($this->tar->addFile($filename, $localname) === false)
                        return false;
                }
            }
        }

        $this->scanArray();
    }

    /**
     * @return int
     */
    public function countFiles()
    {
        return $this->numberOfFiles;
    }

    /**
     * @return int
     */
    public function getArchiveSize()
    {
        return filesize($this->path);
    }

    /**
     * @return string
     */
    public function getArchiveType()
    {
        return 'tar';
    }

    /**
     * @return int
     */
    public function countCompressedFilesSize()
    {
        return $this->compressedFilesSize;
    }

    /**
     * @return int
     */
    public function countUncompressedFilesSize()
    {
        return $this->uncompressedFilesSize;
    }

    /**
     * @param $filesOrFiles
     * @param $archiveName
     * @param bool $fake
     * @return array|bool
     * @throws Exception
     */
    public static function archiveFiles($filesOrFiles, $archiveName, $fake = false)
    {
        $filesOrFiles = self::createFilesList($filesOrFiles);

        // fake creation: return archive data
        if ($fake) {
            $totalSize = 0;
            foreach ($filesOrFiles as $fn) $totalSize += filesize($fn);

            return array(
                'totalSize' => $totalSize,
                'numberOfFiles' => count($filesOrFiles),
                'files' => $filesOrFiles,
            );
        }

        if (class_exists('\Archive_Tar')) {
            $compression = null;
            switch (strtolower(pathinfo($archiveName, PATHINFO_EXTENSION))) {
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
                case 'Z':
                    $tar_aname = 'compress.lzw://' . $archiveName;
                    break;
            }
            if (isset($tar_aname))
                $tar = new Archive_Tar($tar_aname, $compression);
            else
                $tar = new Archive_Tar($archiveName, $compression);

            foreach ($filesOrFiles as $localname => $filename) {
                $remove_dir = dirname($filename);
                $add_dir = dirname($localname);

                if (is_null($filename)) {
                    if ($tar->addString($localname, "") === false)
                        return false;
                } else {
                    if ($tar->addModify($filename, $add_dir, $remove_dir)
                        === false) return false;
                }
            }
            $tar = null;
        } else if (class_exists('\PharData')) {
            if (preg_match('~^(.+)\.(tar\.(gz|bz2|xz|Z))$~i', $archiveName, $match)) {
                $ext = $match[2];
                $basename = $match[1];
            } else {
                $ext = pathinfo($archiveName, PATHINFO_EXTENSION);
                $basename = basename($archiveName, '.'.$ext);
            }
            $tar = new PharData($basename.'.tar', 0, null, Phar::TAR);

            foreach ($filesOrFiles as $localname => $filename) {
                if (is_null($filename)) {
                    if ($tar->addEmptyDir($localname) === false)
                        return false;
                } else {
                    if ($tar->addFile($filename, $localname) === false)
                        return false;
                }
            }

            switch (strtolower(pathinfo($archiveName, PATHINFO_EXTENSION))) {
                case 'gz':
                case 'tgz':
                    $tar->compress(Phar::GZ, $ext);
                    break;
                case 'bz2':
                case 'tbz2':
                    $tar->compress(Phar::BZ2, $ext);
                    break;
            }
        } else {
            throw new Exception('Archive_Tar nor PharData not available');
        }

        return count($filesOrFiles);
    }

    /**
     * Rescans array
     */
    protected function scanArray()
    {
        $this->files = array();
        $this->compressedFilesSize =
        $this->uncompressedFilesSize = 0;

        if ($this->tar instanceof Archive_Tar) {
            $Content = $this->tar->listContent();
            $this->numberOfFiles = count($Content);
            foreach ($Content as $i => $file) {
                // BUG workaround: http://pear.php.net/bugs/bug.php?id=20275
                if ($file['filename'] == 'pax_global_header') {
                    $this->numberOfFiles--;
                    continue;
                }
                $this->files[$i] = $file['filename'];
                $this->uncompressedFilesSize += $file['size'];
            }

            $this->compressedFilesSize = $this->archiveSize;
            if ($this->uncompressedFilesSize != 0)
                $this->compressionRatio = ceil($this->archiveSize
                    / $this->uncompressedFilesSize);
            else
                $this->compressionRatio = 1;
        } else {
            $this->numberOfFiles = $this->tar->count();
            $stream_path_length = strlen('phar://'.$this->path.'/');
            foreach (new RecursiveIteratorIterator($this->tar) as $i => $file) {
                $this->files[$i] = substr($file->getPathname(), $stream_path_length);
                $this->compressedFilesSize += $file->getCompressedSize();
                $this->uncompressedFilesSize += filesize($file->getPathname());
            }
        }
    }
}