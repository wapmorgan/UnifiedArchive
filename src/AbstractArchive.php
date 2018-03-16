<?php
namespace wapmorgan\UnifiedArchive;

abstract class AbstractArchive
{
    abstract public static function open($filename);
	abstract public function __construct($filename, $type);
	abstract public function getFileNames();
	abstract public function getFileData($filename);
	abstract public function getFileContent($filename);
	abstract public function getHierarchy();
	abstract public function extractNode($outputFolder, $node = '/');
	abstract public function deleteFiles($fileOrFiles);
	abstract public function addFiles($nodes);

	abstract public function countFiles();
	abstract public function getArchiveSize();
	abstract public function getArchiveType();
	abstract public function countCompressedFilesSize();
	abstract public function countUncompressedFilesSize();
	abstract public static function archiveNodes($nodes, $archiveName);

	/**
	 * @param $nodes
	 * @return array|bool
	 */
	protected static function createFilesList($nodes)
	{
		// -1: empty folder
		$files = array();
		if (is_array($nodes)) {
			// check integrity
			$strings = 0;// 1 - strings; 2 - arrays
			foreach ($nodes as $node) $strings = (is_string($node) ?
				$strings + 1 : $strings - 1);
			if ($strings > 0 && $strings != count($nodes)) return false;

			if ($strings == count($nodes)) {
				foreach ($nodes as $node) {
					// if is directory
					if (is_dir($node))
						self::importFilesFromDir(rtrim($node, '/*').'/*',
							basename($node).'/', true, $files);
					else if (is_file($node))
						$files[basename($node)] = $node;
				}
			} else {
				// make files list
				foreach ($nodes as $node) {
					if (is_array($node)) $node = (object) $node;
					// put directory inside another directory in archive
					if (substr($node->source, -1) == '/') {
						if (substr($node->destination, -1) != '/')
							return false;
						if (!isset($node->recursive) || !$node->recursive) {
							self::importFilesFromDir($node->source.'*',
								$node->destination.basename($node->source).'/',
								false, $files);
						} else {
							self::importFilesFromDir($node->source.'*',
								$node->destination.basename($node->source).'/',
								true, $files);
						}
					} elseif (substr($node->source, -1) == '*') {
						if (substr($node->destination, -1) != '/')
							return false;
						if (!isset($node->recursive) || !$node->recursive) {
							self::importFilesFromDir($node->source,
								$node->destination, false, $files);
						} else {
							self::importFilesFromDir($node->source,
								$node->destination, true, $files);
						}
					} else { // put regular file inside directory in archive
						if (!is_file($node->source))
							return false;
						$files[$node->destination] = $node->source;
					}
				}
			}
		} elseif (is_string($nodes)) {
			// if is directory
			if (is_dir($nodes))
				self::importFilesFromDir(rtrim($nodes, '/*').'/*', '/', true,
					$files);
			else if (is_file($nodes))
				$files[basename($nodes)] = $nodes;
		}

		return $files;
	}

	/**
	 * @param $source
	 * @param $destination
	 * @param $recursive
	 * @param $map
	 */
	protected static function importFilesFromDir($source, $destination,
												 $recursive, &$map)
	{
		// $map[$destination] = rtrim($source, '/*');
		// do not map root archive folder
		if ($destination != '')
			$map[$destination] = null;
		foreach (glob($source, GLOB_MARK) as $node) {
			if (substr($node, -1) == '/' && $recursive) {
				self::importFilesFromDir($node.'*',
					$destination.basename($node).'/', $recursive, $map);
			} elseif (is_file($node) && is_readable($node)) {
				$map[$destination.basename($node)] = $node;
			}
		}
	}
}
