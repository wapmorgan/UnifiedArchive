<?php
namespace wapmorgan\UnifiedArchive;

class UnifiedArchive implements AbstractArchive {
	const ZIP = 'zip';
	const RAR = 'rar';
	const TAR = 'tar';
	const GZIP = 'gzip';

	protected $type;

	protected $files;
	protected $uncompressedFilesSize;
	protected $compressedFilesSize;
	protected $archiveSize;

	protected $zip;
	protected $rar;
	protected $tar;
	protected $tarCompressionRatio;
	protected $gzipStat;
	protected $gzipFilename;

	/**
	 * Creates instance with right type
	 */
	static public function open($filename) {
		// determine archive type
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if ($ext == 'zip') return new self($filename, self::ZIP);
		if ($ext == 'rar') return new self($filename, self::RAR);
		if ($ext == 'tar' || preg_match('~\.tar\.(gz|bz2)$~', $filename)) return new self($filename, self::TAR);
		if ($ext == 'gz') return new self($filename, self::GZIP);
		if (true) return null;
	}

	/**
	 * Opens archive
	 */
	public function __construct($filename, $type) {
		$this->type = $type;
		$this->archiveSize = filesize($filename);

		switch ($type) {
			case self::ZIP:
				$this->zip = new \ZipArchive;
				$this->zip->open($filename);
				for ($i = 0; $i < $this->zip->numFiles; $i++) {
					// $this->files[] = $this->zip->getNameIndex($i, ZIPARCHIVE::FL_UNCHANGED);
					$file = $this->zip->statIndex($i);
					$this->files[$i] = $file['name'];
					$this->compressedFilesSize += $file['comp_size'];
					$this->uncompressedFilesSize += $file['size'];
				}
			break;
			case self::RAR:
				$this->rar = \RarArchive::open($filename);
				$Entries = $this->rar->getEntries();
				$this->rar->numberOfFiles = count($Entries); # rude hack
				foreach ($Entries as $i => $entry) {
					$this->files[$i] = $entry->getName();
					$this->compressedFilesSize += $entry->getPackedSize();
					$this->uncompressedFilesSize += $entry->getUnpackedSize();
				}
			break;
			case self::TAR:
				$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
				switch ($ext) {
					case 'gz': $this->tar = new \Archive_Tar($filename, 'gz'); break;
					case 'bz2': $this->tar = new \Archive_Tar($filename, 'bz2'); break;
					default: $this->tar = new \Archive_Tar($filename); break;
				}
				$this->tar->path = $filename;

				$Content = $this->tar->listContent();
				$this->tar->numberOfFiles = count($Content);
				foreach ($Content as $i => $file) {
					if ($file['filename'] == 'pax_global_header') {  // BUG workaround: http://pear.php.net/bugs/bug.php?id=20275
						$this->tar->numberOfFiles--;
						continue;
					}
					$this->files[$i] = $file['filename'];
					$this->uncompressedFilesSize += $file['size'];
				}

				$this->compressedFilesSize = $this->archiveSize;
				$this->tarCompressionRatio = ceil($this->archiveSize / $this->uncompressedFilesSize);

			break;
			case self::GZIP:
				$this->files = array(basename($filename, '.gz'));
				$this->gzipStat = gzip_stat($filename);
				$this->gzipFilename = $filename;
				$this->compressedFilesSize = $this->archiveSize;
				$this->uncompressedFilesSize = $this->gzipStat['size'];
			break;
		}
	}

	/**
	 * Closes archive
	 */
	public function __destruct() {
		switch($this->type) {
			case 'zip':
				$this->zip->close();
			break;
			case 'rar':
				$this->rar->close();
			break;
			case 'tar':
				$this->tar = null;
			break;
		}
	}

	/**
	 * Counts number of files
	 */
	public function countFiles() {
		switch($this->type) {
			case 'zip':
				return $this->zip->numFiles;
			break;
			case 'rar':
				return $this->rar->numberOfFiles;
			break;
			case 'tar':
				return $this->tar->numberOfFiles;
			break;
			case 'gzip':
				return 1;
			break;
		}
	}

	/**
	 * Counts size of all uncompressed data
	 */
	public function countUncompressedFilesSize() {
		return $this->uncompressedFilesSize;
	}

	/**
	 * Returns size of archive
	 */
	public function getArchiveSize() {
		return $this->archiveSize;
	}

	/**
	 * Returns type of archive
	 */
	public function getArchiveType() {
		switch ($this->type) {
			case 'zip':
				return self::ZIP;
			break;
			case 'rar':
				return self::RAR;
			break;
			case 'tar':
				return self::TAR;
			break;
			case 'gzip':
				return self::GZIP;
			break;
		}
	}

	/**
	 * Counts size of all compressed data
	 */
	public function countCompressedFilesSize() {
		return $this->compressedFilesSize;
	}

	/**
	 * Returns list of files
	 */
	public function getFileNames() {
		return array_values($this->files);
	}

	/**
	 * Retrieves file data
	 */
	public function getFileData($filename) {
		switch($this->type) {
			case 'zip':
				if (!in_array($filename, $this->files)) return false;
				$index = array_search($filename, $this->files);
				$stat = $this->zip->statIndex($index);

				$file = new \stdClass;
				$file->filename = $filename;
				$file->compressed_size = $stat['comp_size'];
				$file->uncompressed_size = $stat['size'];
				$file->mtime = $stat['mtime'];
				$file->is_compressed = !($stat['comp_method'] == 0); // 0 - no compression; 8 - deflated compression; etc ...
				return $file;
			break;
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
				$file->is_compressed = !($entry->getMethod() == 48); // 0x30 - no compression;
				return $file;
			break;
			case 'tar':
				if (!in_array($filename, $this->files)) return false;
				$index = array_search($filename, $this->files);
				$Content = $this->tar->listContent();
				$data = $Content[$index];
				unset($Content);

				$file = new \stdClass;
				$file->filename = $filename;
				$file->compressed_size = $data['size'] / $this->tarCompressionRatio;
				$file->uncompressed_size = $data['size'];
				$file->mtime = $data['mtime'];
				$file->is_compressed = in_array(strtolower(pathinfo($this->tar->path, PATHINFO_EXTENSION)), array('gz', 'bz2'));
				return $file;
			break;
			case 'gzip':
				if (!in_array($filename, $this->files)) return false;
				$file = new \stdClass;
				$file->filename = $filename;
				$file->compressed_size = $this->archiveSize;
				$file->uncompressed_size = $this->gzipStat['size'];
				$file->mtime = $this->gzipStat['mtime'];
				$file->is_compressed = true;
				return $file;
			break;
		}
	}

	/**
	 * Extracts file content
	 */
	public function getFileContent($filename) {
		switch($this->type) {
			case 'zip':
				if (!in_array($filename, $this->files)) return false;
				$index = array_search($filename, $this->files);
				return $this->zip->getFromIndex($index);
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
			case 'tar':
				if (!in_array($filename, $this->files)) return false;
				return $this->tar->extractInString($filename);
			break;
			case 'gzip':
				if (!in_array($filename, $this->files)) return false;
				return gzdecode(file_get_contents($this->gzipFilename));
			break;
		}
	}

	/**
	 * Returns hierarchy
	 */
	public function getHierarchy() {
		$tree = array(DIRECTORY_SEPARATOR);
		foreach ($this->files as $filename) {
			if (in_array(substr($filename, -1), array('/', '\\')))
				$tree[] = DIRECTORY_SEPARATOR.$filename;
		}
		return $tree;
	}

	/**
	 * Unpacks node with its content to disk. Pass any node from getHierarchy() method.
	 */
	public function extractNode($outputFolder, $node = '/') {
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
				if (($result = $this->zip->extractTo($outputFolder, $entries)) === true) {
					return count($entries);
				} else {
					return false;
				}
			break;
			case 'rar':
				$count = 0;
				foreach ($this->files as $fname) {
					if ($node === '' || strpos($fname, $node) === 0) {
						if ($this->rar->getEntry($fname)->extract($outputFolder)) {
							$count++;
						}
					}
				}
				return $count;
			break;
			case 'tar':
				$list = array();
				if ($node === '') {
					$list = array_values($this->files);
				} else {
					foreach ($this->files as $fname) {
						if (strpos($fname, $node) === 0) {
							$list[] = $fname;
						}
					}
				}
				if (($result = $this->tar->extractList($list, $outputFolder)) === true) {
					return count($list);
				} else {
					return false;
				}
			break;
			case 'gzip':
				if ($node === '') {
					$dir = rtrim($outputFolder, '/').'/';
					if (!is_dir($dir)) mkdir($dir);
					if (file_put_contents($dir.basename($this->gzipFilename, '.gz'), gzdecode(file_get_contents($this->gzipFilename))) !== false)
						return 1;
					else
						return false;
				} else {
					return 0;
				}
			break;
		}
	}

	/**
	 * Creates an archive.
	 */
	static public function archiveNodes($nodes, $aname, $fake = false) {
		// -1: empty folder
		$files = array();
		if (is_array($nodes)) {
			// check integrity
			$strings = 0;// 1 - strings; 2 - arrays
			foreach ($nodes as $node) $strings = (is_string($node) ? $strings + 1 : $strings - 1);
			if ($strings > 0 && $strings != count($nodes)) return false;

			if ($strings == count($nodes)) {
				foreach ($nodes as $node) {
					// if is directory
					if (is_dir($node))
						self::importFilesFromDir(rtrim($node, '/*').'/*', basename($node).'/', true, $files);
					else if (is_file($node))
						$files[basename($node)] = $node;
				}
			} else {
				// make files list
				foreach ($nodes as $node) {
					if (is_array($node)) $node = (object)$node;
					// put directory inside another directory in archive
					if (substr($node->source, -1) == '/') {
						if (substr($node->destination, -1) != '/')
							return false;
						if (!isset($node->recursive) || !$node->recursive) {
							self::importFilesFromDir($node->source.'*', $node->destination.basename($node->source).'/', false, $files);
						} else {
							self::importFilesFromDir($node->source.'*', $node->destination.basename($node->source).'/', true, $files);
						}
					} else if (substr($node->source, -1) == '*') { // put files inside directory in archive
						if (substr($node->destination, -1) != '/')
							return false;
						if (!isset($node->recursive) || !$node->recursive) {
							self::importFilesFromDir($node->source, $node->destination, false, $files);
						} else {
							self::importFilesFromDir($node->source, $node->destination, true, $files);
						}
					} else { // put regular file inside directory in archive
						if (!is_file($node->source))
							return false;
						$files[$node->destination] = $node->source;
					}
				}
			}
		} else if (is_string($nodes)) {
			// if is directory
			if (is_dir($nodes))
				self::importFilesFromDir(rtrim($nodes, '/*').'/*', '/', true, $files);
			else if (is_file($nodes))
				$files[basename($nodes)] = $nodes;
		}
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

		$ext = strtolower(pathinfo($aname, PATHINFO_EXTENSION));
		if ($ext == 'zip') $atype = self::ZIP;
		else if ($ext == 'rar') $atype = self::RAR;
		else if ($ext == 'tar' || preg_match('~\.tar\.(gz|bz2)$~', $aname)) $atype = self::TAR;
		else if ($ext == 'gz') $atype = self::GZIP;
		else return false;

		switch ($atype) {
			case self::ZIP:
				$zip = new \ZipArchive;
				if (($result = $zip->open($aname, \ZIPARCHIVE::CREATE)) !== true)
					throw new \Exception('ZipArchive error: '.$result);
				foreach ($files as $localname => $filename) {
					/*echo "added ".$filename.PHP_EOL;
					echo number_format(filesize($filename)).PHP_EOL;
					*/
					if (is_null($filename)) {
						if ($zip->addEmptyDir($localname) === false) return false;
					} else {
						if ($zip->addFile($filename, $localname) === false) return false;
					}
				}
				$zip->close();
				return count($files);
			break;
			case self::RAR:
				return false;
			break;
			case self::TAR:
				$compression = null;
				switch (strtolower(pathinfo($aname, PATHINFO_EXTENSION))) {
					case 'gz': $compression = 'gz'; break;
					case 'bz2': $compression = 'bz2'; break;
				}
				$tar = new \Archive_Tar($aname, $compression);
				foreach ($files as $localname => $filename) {
					$remove_dir = dirname($filename);
					$add_dir = dirname($localname);
					/*echo "added ".$filename.PHP_EOL;
					echo number_format(filesize($filename)).PHP_EOL;
					*/
					if (is_null($filename)) {
						if ($tar->addString($localname, "") === false) return false;
					} else {
						if ($tar->addModify($filename, $add_dir, $remove_dir) === false) return false;
					}
				}
				$tar = null;
				return count($files);
			break;
			case self::GZIP:
				if (count($files) > 1) return false;
				/*if ($localname != basename($aname, '.gz')) return false;
				*/
				$filename = array_shift($files);
				if (is_null($filename)) return false; // invalid list
				if (file_put_contents($aname, gzencode(file_get_contents($filename))) !== false)
					return 1;
				else
					return false;
			break;
		}
	}

	static private function importFilesFromDir($source, $destination, $recursive, &$map) {
		// $map[$destination] = rtrim($source, '/*');
		// do not map root archive folder
		if ($destination != '')
		$map[$destination] = null;

		foreach (glob($source, GLOB_MARK) as $node) {
			if (substr($node, -1) == '/' && $recursive) {
				self::importFilesFromDir($node.'*', $destination.basename($node).'/', $recursive, $map);
			} else if (is_file($node) && is_readable($node)) {
				$map[$destination.basename($node)] = $node;
			}
		}
	}
}
