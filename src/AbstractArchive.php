<?php
namespace wapmorgan\UnifiedArchive;

interface AbstractArchive
{
    public static function open($filename);
    public function __construct($filename, $type);
    public function getFileNames();
    public function getFileData($filename);
    public function getFileContent($filename);
    public function getHierarchy();
    public function extractNode($outputFolder, $node = '/');
    public function deleteFiles($fileOrFiles);
    public function addFiles($nodes);

    public function countFiles();
    public function getArchiveSize();
    public function getArchiveType();
    public function countCompressedFilesSize();
    public function countUncompressedFilesSize();
    public static function archiveNodes($nodes, $aname);
}
