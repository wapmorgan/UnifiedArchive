#!/usr/bin/php
<?php
use \wapmorgan\UnifiedArchive\UnifiedArchive;
require dirname(dirname(__FILE__)).'/vendor/autoload.php';

echo "Usage: ".$argv[0]." ".(isset($argv[1]) ? rtrim($argv[1], '/*') : '.').PHP_EOL;

function formatBytes($bytes, $precision = 2) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB');
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	// $bytes /= (1 << (10 * $pow));
	return round($bytes, $precision) . $units[$pow];
}

echo sprintf("%28s | %4s | %8s | %8s | %8s | %8s | %8s | %8s | %8s | %8s", "Name", "Type", "Size", "Unpacked", "Packed", "Files", "Ratio", "Size", "Unpacked", "Packed").PHP_EOL;
foreach (glob(isset($argv[1]) ? rtrim($argv[1], '/*').'/*' : './*') as $file) {
	if (is_file($file)) {
		if (($archive = UnifiedArchive::open($file)) === null) {
			continue;
		}
		echo sprintf("%-28s | %4s | %8d | %8d | %8d | %8d | %7dx | %8s | %8s | %8s", basename($file), $archive->getArchiveType(),
			$archive->getArchiveSize(), $archive->countUncompressedFilesSize(), $archive->countCompressedFilesSize(), $archive->countFiles(),
			$archive->countCompressedFilesSize() > 0 ? ceil($archive->countUncompressedFilesSize() / $archive->countCompressedFilesSize()) : 0,
			formatBytes($archive->getArchiveSize()), formatBytes($archive->countUncompressedFilesSize()), formatBytes($archive->countCompressedFilesSize())
			).PHP_EOL;

	}
}
