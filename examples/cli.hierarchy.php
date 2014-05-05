#!/usr/bin/php
<?php
use \wapmorgan\UnifiedArchive\UnifiedArchive;
require dirname(dirname(__FILE__)).'/vendor/autoload.php';

$opts = getopt("lthdeuia:o:n:");

echo "Usage: ".$argv[0]." -l/-t/-h/-d/-e/-u/-i {-a archive} [-n nodeForDetails|nodeForExtract|nodeForUnpack] [-o outputDirForExtract]".PHP_EOL;
echo "nodes actions: list / table / hierarchy; node actions: details / extract / unpack; stat actions: information;".PHP_EOL;

if (!isset($opts['a'])) exit;
$archive = \wapmorgan\UnifiedArchive\UnifiedArchive::open($opts['a']);

$files = $archive->getFileNames();
sort($files);
if (isset($opts['l']))
var_export($files);
else if (isset($opts['t'])) {
	echo sprintf("%8s | %8s | %31s | %10s | %s", "Unpacked", "Packed", "Mtime", "Compressed", "Node name").PHP_EOL;
	foreach ($files as $file) {
		$fdata = $archive->getFileData($file);
		echo sprintf("%8s | %8s | %31s | %10s | %s", $fdata->uncompressed_size, $fdata->compressed_size, date('r', $fdata->mtime), (int)$fdata->is_compressed, $fdata->filename).PHP_EOL;
	}
} else if (isset($opts['h']))
var_export($archive->getHierarchy());
else if (isset($opts['e']) && isset($opts['n']) && isset($opts['o'])) {
	$result = $archive->extractNode($opts['o'], $opts['n']);
	if ($result === false) echo "fail".PHP_EOL;
	else echo "Extracted ".$result." file(s)".PHP_EOL;
} else if (isset($opts['d']) && isset($opts['n'])) {
	if (($node = $archive->getFileData($opts['n'])) !== false) {
		echo "Node ".$opts['n'].PHP_EOL;
		echo "Compressed size: ".number_format($node->compressed_size).PHP_EOL;
		echo "Uncompressed size: ".number_format($node->uncompressed_size).PHP_EOL;
		echo "Modification time: ".date('r', $node->mtime).PHP_EOL;
		echo "Is compressed: ".(int)$node->is_compressed.PHP_EOL;
	} else echo "Error!".PHP_EOL;
} else if (isset($opts['u']) && isset($opts['n'])) {
	echo $archive->getFileContent($opts['n']);
} else if (isset($opts['i'])) {
	echo "Files: ".number_format($archive->countFiles()).PHP_EOL;
	echo "Archive size: ".number_format($archive->getArchiveSize()).PHP_EOL;
	echo "Compressed files size: ".number_format($archive->countCompressedFilesSize()).PHP_EOL;
	echo "Uncompressed files size: ".number_format($archive->countUncompressedFilesSize()).PHP_EOL;
	echo "Ratio: ".ceil($archive->countUncompressedFilesSize() / $archive->countCompressedFilesSize())."x".PHP_EOL;
}
