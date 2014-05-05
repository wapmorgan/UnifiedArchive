<?php
namespace wapmorgan\UnifiedArchive;

interface AbstractArchive {
	static public function open($filename);
	public function __construct($filename, $type);
	public function getFileNames();
	public function getFileData($filename);
	public function getFileContent($filename);
	public function getHierarchy();
	public function extractNode($outputFolder, $node = '/');

	public function countFiles();
	public function getArchiveSize();
	public function getArchiveType();
	public function countCompressedFilesSize();
	public function countUncompressedFilesSize();
	static public function archiveNodes($nodes, $aname);
}
