<?php
namespace wapmorgan\UnifiedArchive;
use Archive7z\Archive7z;
use ZipArchive;

/**
 * Class which represents archive in one of supported formats.
 */
class UnifiedArchive extends AbstractArchive
{
    const ZIP = 'zip';
    const SEVEN_ZIP = '7zip';
    const RAR = 'rar';
    const GZIP = 'gzip';
    const BZIP = 'bzip2';
    const LZMA = 'lzma2';
    const ISO = 'iso';
    const CAB = 'cab';

    /** @var string */
    protected $type;

    /** @var array */
    protected $files;

	/** @var int */
    protected $uncompressedFilesSize;

	/** @var int */
    protected $compressedFilesSize;

	/** @var int */
    protected $archiveSize;

    /** @var ZipArchive */
    protected $zip;

    /** @var Archive7z */
    protected $seven_zip;

    /** @var \RarArchive */
    protected $rar;

    /** @var array|null */
    protected $gzipStat;

    /** @var string */
    protected $gzipFilename;

    /** @var array */
    protected $bzipStat;

    /** @var string */
    protected $bzipFilename;

    /** @var string */
    protected $lzmaFilename;

    /** @var \CISOFile */
    protected $iso;

    /** @var int */
    protected $isoBlockSize;

    /** @var mixed */
    protected $isoFilesData;

    /** @var \CabArchive */
    protected $cab;

    /**
     * Creates instance with right type.
     *
     * @param  string $filename Filename
     *
     * @return AbstractArchive|null Returns AbstractArchive in case of successful
     * parsing of the file
     */
    public static function open($filename)
    {
        // determine archive type
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext == 'zip' && extension_loaded('zip'))
            return new self($filename, self::ZIP);
        if ($ext == '7z' && class_exists('\Archive7z\Archive7z'))
            return new self($filename, self::SEVEN_ZIP);
        if ($ext == 'rar' && extension_loaded('rar'))
            return new self($filename, self::RAR);
        if ((in_array($ext, array('tar', 'tgz', 'tbz2', 'txz')) || preg_match('~\.tar\.(gz|bz2|xz|Z)$~', $filename))
			&& ($archive = TarArchive::open($filename)) !== null)
			return $archive;
        if ($ext == 'gz' && extension_loaded('zlib'))
            return new self($filename, self::GZIP);
        if ($ext == 'bz2' && extension_loaded('bz2'))
            return new self($filename, self::BZIP);
        if ($ext == 'xz' && extension_loaded('xz'))
            return new self($filename, self::LZMA);
        if ($ext == 'iso' && class_exists('\CISOFile'))
            return new self($filename, self::ISO);
        if ($ext == 'cab' && class_exists('\CabArchive'))
            return new self($filename, self::CAB);
        return null;
    }

    /**
     * Opens the file as one of supported formats.
     *
     * @param string $filename Filename
     * @param string $type     Archive type.
     */
    public function __construct($filename, $type)
    {
        $this->type = $type;
        $this->archiveSize = filesize($filename);

        switch ($type) {
            case self::ZIP:
                $this->zip = new ZipArchive;
                if ($this->zip->open($filename) === true) {
                    for ($i = 0; $i < $this->zip->numFiles; $i++) {
                        $file = $this->zip->statIndex($i);
                        $this->files[$i] = $file['name'];
                        $this->compressedFilesSize += $file['comp_size'];
                        $this->uncompressedFilesSize += $file['size'];
                    }
                } else {
                    $this->zip->numFiles =
                    $this->compressedFilesSize =
                    $this->uncompressedFilesSize = 0;
                }
            break;
            case self::SEVEN_ZIP:
                $this->seven_zip = new Archive7z($filename);
                foreach ($this->seven_zip->getEntries() as $entry) {
                    $this->files[] = $entry->getPath();
                    $this->compressedFilesSize += (int)$entry->getPackedSize();
                    $this->uncompressedFilesSize += (int)$entry->getSize();
                }
                $this->seven_zip->numFiles = count($this->files);
            break;
            case self::RAR:
                $this->rar = \RarArchive::open($filename);
                $Entries = @$this->rar->getEntries();
                if ($Entries === false) {
                    $this->rar->numberOfFiles =
                    $this->compressedFilesSize =
                    $this->uncompressedFilesSize = 0;
                } else {
                    $this->rar->numberOfFiles = count($Entries); # rude hack
                    foreach ($Entries as $i => $entry) {
                        $this->files[$i] = $entry->getName();
                        $this->compressedFilesSize += $entry->getPackedSize();
                        $this->uncompressedFilesSize +=
                            $entry->getUnpackedSize();
                    }
                }
            break;
            case self::GZIP:
                $this->files = array(basename($filename, '.gz'));
                $this->gzipStat = gzip_stat($filename);
                $this->gzipFilename = $filename;
                $this->compressedFilesSize = $this->archiveSize;
                $this->uncompressedFilesSize = $this->gzipStat['size'];
            break;
            case self::BZIP:
                $this->files = array(basename($filename, '.bz2'));
                $this->bzipFilename = $filename;
                $this->bzipStat = array('mtime' => filemtime($filename));
                $this->compressedFilesSize = $this->archiveSize;
                $this->uncompressedFilesSize = $this->archiveSize;
            break;
            case self::LZMA:
                $this->files = array(basename($filename, '.xz'));
                $this->lzmaFilename = $filename;
                $this->bzipStat = array('mtime' => filemtime($filename));
                $this->compressedFilesSize = $this->archiveSize;
                $this->uncompressedFilesSize = $this->archiveSize;
            break;
            case self::ISO:
                // load php-iso-files
                $this->iso = new \CISOFile;
                $this->iso->open($filename);
                $this->iso->ISOInit();
                $size = 0;

                $usedDesc =
                    $this->iso->GetDescriptor(SUPPLEMENTARY_VOLUME_DESC);
                if(!$usedDesc)
                    $usedDesc = $this->iso->GetDescriptor(PRIMARY_VOLUME_DESC);
                $this->isoBlockSize = $usedDesc->iBlockSize;
                $directories = $usedDesc->LoadMPathTable($this->iso);
                foreach ($directories as $Directory) {
                    $directory = $Directory->GetFullPath($directories, false);
                    $directory = trim($directory, '/');
                    if ($directory != '') {
                        $directory .= '/';
                        $this->files[$Directory->Location] = $directory;
                    }
                    $this->isoCatalogsStructure[$Directory->Location]
                        = $directory;

                    $files = $Directory->LoadExtents($this->iso,
                        $usedDesc->iBlockSize, true);
                    if ($files) {
                        foreach ($files as $file) {
                            if (in_array($file->strd_FileId, array('.', '..')))
                                continue;

                            $this->files[$file->Location]
                                = $directory.$file->strd_FileId;
                            $size += $file->DataLen;

                            $this->isoFilesData[$directory.$file->strd_FileId] =
                            array(
                                'size' => $file->DataLen,
                                'mtime' =>
                                strtotime((string) $file->isoRecDate),
                            );
                        }
                    }
                    break;
                }
                $this->uncompressedFilesSize = $this->compressedFilesSize
                 = $size;

            break;
            case self::CAB:
                $this->cab = new \CabArchive($filename);
                foreach ($this->cab->getFileNames() as $file) {
                    $this->files[] = $file;
                    $file_info = $this->cab->getFileData($file);
                    $this->uncompressedFilesSize += $file_info->size;
                    $this->compressedFilesSize += $file_info->packedSize;
                }
                break;
        }
    }

	/**
     * Returns an instance of class implementing PclZipOriginalInterface
     * interface.
     *
     * @return PclZipOriginalInterface Returns an instance of a class
     * implementing PclZipOriginalInterface
     */
    public function pclzipInterace()
    {
        switch ($this->type) {
            case 'zip':
                return new PclZipLikeZipArchiveInterface($this->zip);
            break;
        }

        throw new \Exception(basename(__FILE__).', line '.__LINE.' : PclZip-like interface IS'.
         'NOT available for '.$this->type.' archive format');
    }

    /**
     * Closes archive.
     */
    public function __destruct()
    {
        switch ($this->type) {
            case 'zip':
                // $this->zip->close();
                unset($this->zip);
            break;
            case '7zip':
                unset($this->seven_zip);
            break;
            case 'rar':
                $this->rar->close();
            break;
            case 'iso':
                $this->iso->close();
            break;
            case 'cab':
                unset($this->cab);
            break;
        }
    }

    /**
     * Counts number of files
     */
    public function countFiles()
    {
        switch ($this->type) {
            case 'zip':
                return $this->zip->numFiles;

            case '7zip':
                return $this->seven_zip->numFiles;

            case 'rar':
                return $this->rar->numberOfFiles;

            case 'gzip':
                return 1;

            case 'bzip2':
                return 1;

            case 'lzma2':
                return 1;

            case 'iso':
                return count($this->files);

            case 'cab':
                return $this->cab->filesCount;
        }
    }

    /**
     * Counts size of all uncompressed data
     */
    public function countUncompressedFilesSize()
    {
        return $this->uncompressedFilesSize;
    }

    /**
     * Returns size of archive
     */
    public function getArchiveSize()
    {
        return $this->archiveSize;
    }

    /**
     * Returns type of archive
     */
    public function getArchiveType()
    {
        switch ($this->type) {
            case 'zip':
                return self::ZIP;

            case '7zip':
                return self::SEVEN_ZIP;

            case 'rar':
                return self::RAR;

            case 'gzip':
                return self::GZIP;

            case 'bzip2':
                return self::BZIP;

            case 'lzma2':
                return self::LZMA;

            case 'iso':
                return self::ISO;

            case 'cab':
                return self::CAB;
        }
    }

    /**
     * Counts size of all compressed data
     */
    public function countCompressedFilesSize()
    {
        return $this->compressedFilesSize;
    }

    /**
     * Returns list of files
     */
    public function getFileNames()
    {
        return array_values($this->files);
    }

    /**
     * Retrieves file data
     */
    public function getFileData($filename)
    {
        switch ($this->type) {
            case 'zip':
                if (!in_array($filename, $this->files)) return false;
                $index = array_search($filename, $this->files);
                $stat = $this->zip->statIndex($index);

                $file = new \stdClass;
                $file->filename = $filename;
                $file->compressed_size = $stat['comp_size'];
                $file->uncompressed_size = $stat['size'];
                $file->mtime = $stat['mtime'];
                // 0 - no compression; 8 - deflated compression; etc ...
                $file->is_compressed = !($stat['comp_method'] == 0);

                return $file;
            case '7zip':
                if (!in_array($filename, $this->files)) return false;
                $entry = $this->seven_zip->getEntry($filename);

                $file = new \stdClass;
                $file->filename = $filename;
                $file->uncompressed_size = $entry->getSize();
                $file->compressed_size = ceil($file->uncompressed_size * ($this->compressedFilesSize / $this->uncompressedFilesSize));
                $file->mtime = strtotime($entry->getModified());
                $file->is_compressed = $file->uncompressed_size != $file->compressed_size;

                return $file;
            case 'rar':
                if (!in_array($filename, $this->files)) return false;
                $entry = $this->rar->getEntry($filename);
                $file = new \stdClass;
                $file->filename = $filename;
                $file->compressed_size = $entry->getPackedSize();
                $file->uncompressed_size = $entry->getUnpackedSize();
                // convert time to unixtime
                $unixtime_format = strtotime($entry->getFileTime());
                $file->mtime = $unixtime_format;
                // 0x30 - no compression;
                $file->is_compressed = !($entry->getMethod() == 48);

                return $file;
            case 'gzip':
                if (!in_array($filename, $this->files)) return false;
                $file = new \stdClass;
                $file->filename = $filename;
                $file->compressed_size = $this->archiveSize;
                $file->uncompressed_size = $this->gzipStat['size'];
                $file->mtime = $this->gzipStat['mtime'];
                $file->is_compressed = true;

                return $file;
            case 'bzip2':
                if (!in_array($filename, $this->files)) return false;
                $file = new \stdClass;
                $file->filename = $filename;
                $file->compressed_size = $this->archiveSize;
                $file->uncompressed_size = $this->archiveSize;
                $file->mtime = $this->bzipStat['mtime'];
                $file->is_compressed = true;

                return $file;
            break;
            case 'lzma2':
                if (!in_array($filename, $this->files)) return false;
                $file = new \stdClass;
                $file->filename = $filename;
                $file->compressed_size = $this->archiveSize;
                $file->uncompressed_size = $this->archiveSize;
                $file->mtime = 0;
                $file->is_compressed = true;

                return $file;
            case 'iso':
                if (!in_array($filename, $this->files)) return false;
                if (!isset($this->isoFilesData[$filename])) return false;
                $data = $this->isoFilesData[$filename];
                $file = new \stdClass;
                $file->filename = $filename;
                $file->compressed_size = $data['size'];
                $file->uncompressed_size = $data['size'];
                $file->mtime = $data['mtime'];
                $file->is_compressed = false;

                return $file;
            case 'cab':
                if (!in_array($filename, $this->files)) return false;
                $data = $this->cab->getFileData($filename);
                $file = new \stdClass;
                $file->filename = $filename;
                $file->compressed_size = $data->packedSize;
                $file->uncompressed_size = $data->size;
                $file->mtime = $data->unixtime;
                $file->is_compressed = $data->is_compressed;
                return $file;
        }
    }

    /**
     * Extracts file content
     */
    public function getFileContent($filename)
    {
        switch ($this->type) {
            case 'zip':
                if (!in_array($filename, $this->files)) return false;
                $index = array_search($filename, $this->files);

                return $this->zip->getFromIndex($index);
            break;
            case '7zip':
                if (!in_array($filename, $this->files)) return false;
                $entry = $this->seven_zip->getEntry($filename);
                return $entry->getContent();
            break;
            case 'rar':
                if (!in_array($filename, $this->files)) return false;
                $entry = $this->rar->getEntry($filename);
                if ($entry->isDirectory()) return false;
                // create temp file
                $tmpname = tempnam(sys_get_temp_dir(), 'RarFile');
                $entry->extract(dirname(__FILE__), $tmpname);
                $data = file_get_contents($tmpname);
                unlink($tmpname);

                return $data;
            break;
            case 'gzip':
                if (!in_array($filename, $this->files)) return false;
                return gzdecode(file_get_contents($this->gzipFilename));
            break;
            case 'bzip2':
                if (!in_array($filename, $this->files)) return false;
                return bzdecompress(file_get_contents($this->bzipFilename));
            break;
            case 'lzma2':
                if (!in_array($filename, $this->files)) return false;
                $fp = xzopen($this->lzmaFilename, 'r');
                ob_start();
                xzpassthru($fp);
                $content = ob_get_flush();
                xzclose($fp);
                return $content;
            break;
            case 'iso':
                if (!in_array($filename, $this->files)) return false;
                $Location = array_search($filename, $this->files);
                if (!isset($this->isoFilesData[$filename])) return false;
                $data = $this->isoFilesData[$filename];
                $Location_Real = $Location * $this->isoBlockSize;
                if ($this->iso->Seek($Location_Real, SEEK_SET) === false)
                    return false;
                return $this->iso->Read($data['size']);
            break;
            case 'cab':
                return false;
        }
    }

    /**
     * Returns hierarchy
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
     * Unpacks node with its content to disk. Pass any node from getHierarchy()
     * method.
     */
    public function extractNode($outputFolder, $node = '/')
    {
        if ($node != '/') $node = substr($node, 1);
        else $node = '';
        switch ($this->type) {
            case 'zip':
                $entries = array();
                if ($node === '') {
                    $entries = array_values($this->files);
                } else {
                    foreach ($this->files as $fname) {
                        if (strpos($fname, $node) === 0) {
                            $entries[] = $fname;
                        }
                    }
                }
                $result = $this->zip->extractTo($outputFolder, $entries);
                if ($result === true) {
                    return count($entries);
                } else {
                    return false;
                }
            break;
            case '7zip':
                $this->seven_zip->setOutputDirectory($outputFolder);
                $count = 0;
                if ($node === '') {
                    return $this->seven_zip->extract();
                } else {
                    foreach ($this->files as $fname) {
                        if (strpos($fname, $node) === 0) {
                            if ($this->seven_zip->extractEntry($fname))
                                $count++;
                        }
                    }
                }
                return $count;
            break;
            case 'rar':
                $count = 0;
                foreach ($this->files as $fname) {
                    if ($node === '' || strpos($fname, $node) === 0) {
                        if ($this->rar->getEntry($fname)
                            ->extract($outputFolder)) {
                            $count++;
                        }
                    }
                }
                return $count;
            break;
            case 'gzip':
                if ($node === '') {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir)) mkdir($dir);
                    if (file_put_contents($dir.
                        basename($this->gzipFilename, '.gz'),
                        gzdecode(file_get_contents($this->gzipFilename)))
                        !== false)
                        return 1;
                    else
                        return false;
                } else {
                    return 0;
                }
            break;
            case 'bzip2':
                if ($node === '') {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir)) mkdir($dir);
                    if (file_put_contents($dir.
                        basename($this->bzipFilename, '.bz2'),
                        bzdecompress(file_get_contents($this->bzipFilename)))
                        !== false)
                        return 1;
                    else
                        return false;
                } else {
                    return 0;
                }
            break;
            case 'lzma2':
                if ($node === '') {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir)) mkdir($dir);
                    $fp = xzopen($this->lzmaFilename, 'r');
                    ob_start();
                    xzpassthru($fp);
                    $content = ob_get_flush();
                    xzclose($fp);
                    if (file_put_contents($dir.
                        basename($this->lzmaFilename, '.xz'),
                        $content)
                        !== false)
                        return 1;
                    else
                        return false;
                } else {
                    return 0;
                }
            break;
            case 'cab':
                return false;
        }
    }

    /**
     * Updates existing archive by removing files from it.
     */
    public function deleteFiles($fileOrFiles)
    {
        $files = is_string($fileOrFiles) ? array($fileOrFiles) : $fileOrFiles;
        foreach ($files as $i => $file) {
            if (!in_array($file, $this->files)) unset($files[$i]);
        }
        switch ($this->type) {
            case 'zip':
                $count = 0;
                foreach ($files as $file) {
                    $index = array_search($file, $this->files);
                    $stat = $this->zip->statIndex($index);
                    if ($this->zip->deleteIndex($index)) {
                        unset($this->files[$index]);
                        $this->compressedFilesSize -= $stat['comp_size'];
                        $this->uncompressedFilesSize -= $stat['size'];
                        $count++;
                    }
                }
            break;
            case '7zip':
                foreach ($files as $file) {
                    $this->seven_zip->delEntry($file);
                    unset($this->files[array_search($file, $this->files)]);
                }

                $this->compressedFilesSize =
                $this->uncompressedFilesSize = 0;
                foreach ($this->seven_zip->getEntries() as $entry) {
                    $this->compressedFilesSize += $entry->getPackedSize();
                    $this->uncompressedFilesSize += $entry->getSize();
                }

                $count = $this->seven_zip->numFiles - count($this->files);
                $this->seven_zip->numFiles = count($this->files);
            break;
        }
        return isset($count) ? $count : false;
    }

	/**
	 * Updates existing archive by adding new files.
	 * @param array $nodes
	 * @return int
	 */
	public function addFiles($nodes)
    {
        $files = self::createFilesList($nodes);

        switch ($this->type) {
            case self::ZIP:
                foreach ($files as $localname => $filename) {
                    if (is_null($filename)) {
                        if ($this->zip->addEmptyDir($localname) === false)
                            return false;
                    } else {
                        if ($this->zip->addFile($filename, $localname) === false)
                            return false;
                    }
                }

                $this->files = array();
                $this->compressedFilesSize =
                $this->uncompressedFilesSize = 0;
                for ($i = 0; $i < $this->zip->numFiles; $i++) {
                    $file = $this->zip->statIndex($i);
                    $this->files[$i] = $file['name'];
                    $this->compressedFilesSize += $file['comp_size'];
                    $this->uncompressedFilesSize += $file['size'];
                }
            break;
            case self::SEVEN_ZIP:
                foreach ($files as $localname => $filename) {
                    if (!is_null($filename)) {
                        $this->seven_zip->addEntry($filename, false, $localname);
                    }
                }

                $this->files = array();
                $this->compressedFilesSize =
                $this->uncompressedFilesSize = 0;
                foreach ($this->seven_zip->getEntries() as $entry) {
                    $this->files[] = $entry->getPath();
                    $this->compressedFilesSize += $entry->getPackedSize();
                    $this->uncompressedFilesSize += $entry->getSize();
                }
                $this->seven_zip->numFiles = count($this->files);
            break;
        }
        return count($this->files);
    }

	/**
	 * Creates an archive.
	 * @param array $nodes
	 * @param $archiveName
	 * @param bool $fake
	 * @return array|bool|int
	 * @throws \Exception
	 */
	public static function archiveNodes($nodes, $archiveName, $fake = false)
    {
		$ext = strtolower(pathinfo($archiveName, PATHINFO_EXTENSION));
		if ($ext == 'zip') $atype = self::ZIP;
		else if ($ext == '7z') $atype = self::SEVEN_ZIP;
		else if ($ext == 'rar') $atype = self::RAR;
		else if (in_array($ext, ['tar', 'tgz', 'tbz2', 'txz'], true) || preg_match('~\.tar\.(gz|bz2|xz|Z)$~i', $archiveName))
			return TarArchive::archiveNodes($nodes, $archiveName, $fake);
		else if ($ext == 'gz') $atype = self::GZIP;
		else if ($ext == 'bz2') $atype = self::BZIP;
		else if ($ext == 'xz') $atype = self::LZMA;
		else return false;

        $files = self::createFilesList($nodes);

		// fake creation: return archive data
		if ($fake) {
			$totalSize = 0;
			foreach ($files as $fn) $totalSize += filesize($fn);

			return array(
				'totalSize' => $totalSize,
				'numberOfFiles' => count($files),
				'files' => $files,
			);
		}

        switch ($atype) {
            case self::ZIP:
                $zip = new ZipArchive;
                $result = $zip->open($archiveName, ZipArchive::CREATE);
                if ($result !== true)
                    throw new \Exception('ZipArchive error: '.$result);
                foreach ($files as $localname => $filename) {
                    /*echo "added ".$filename.PHP_EOL;
                    echo number_format(filesize($filename)).PHP_EOL;
                    */
                    if (is_null($filename)) {
                        if ($zip->addEmptyDir($localname) === false)
                            return false;
                    } else {
                        if ($zip->addFile($filename, $localname) === false)
                            return false;
                    }
                }
                $zip->close();

                return count($files);
            break;
            case self::SEVEN_ZIP:
                $seven_zip = new Archive7z($archiveName);
                foreach ($files as $localname => $filename) {
                    if (!is_null($filename)) {
                        $seven_zip->addEntry($filename, false, $localname);
                    }
                }
                unset($seven_zip);
                return count($files);
            break;
            case self::RAR:
                return false;
            break;
            case self::GZIP:
                if (count($files) > 1) return false;
                /*if ($localname != basename($aname, '.gz')) return false;
                */
                $filename = array_shift($files);
                if (is_null($filename)) return false; // invalid list
                if (file_put_contents($archiveName,
                    gzencode(file_get_contents($filename))) !== false)
                    return 1;
                else
                    return false;
            break;
            case self::BZIP:
                if (count($files) > 1) return false;
                /*if ($localname != basename($aname, '.bz2')) return false;
                */
                $filename = array_shift($files);
                if (is_null($filename)) return false; // invalid list
                if (file_put_contents($archiveName,
                    bzcompress(file_get_contents($filename))) !== false)
                    return 1;
                else
                    return false;
            break;
            case self::LZMA:
                if (count($files) > 1) return false;
                /*if ($localname != basename($aname, '.xz')) return false;
                */
                $filename = array_shift($files);
                if (is_null($filename)) return false; // invalid list
                $fp = xzopen($archiveName, 'w');
                $r = xzwrite($fp, file_get_contents($filename));
                xzclose($fp);
                if ($r !== false)
                    return 1;
                else
                    return false;
            break;
        }
    }
}
