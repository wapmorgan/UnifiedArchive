#!/usr/bin/php
<?php
use \wapmorgan\UnifiedArchive\UnifiedArchive;
require dirname(dirname(__FILE__)).'/vendor/autoload.php';

$opts = getopt('omefa:n:s:');

echo "Usage: ".$argv[0]." -o/-m/-e {-a archive} [-f] [-n nodes] [-s nodesSeparator]".PHP_EOL;

if (!isset($opts['a'])) exit;
$filename = $opts['a'];
if (file_exists($filename)) unlink($filename);

$fake = isset($opts['f']);
var_dump($opts);

if (isset($opts['o']) && isset($opts['n'])) {
	$result = UnifiedArchive::archiveNodes($opts['n'], $filename, $fake);
	if ($fake) var_export($result);
	else {
		echo "result: ".var_export($result, true).PHP_EOL;
		echo "saved to ".$filename.PHP_EOL;
		echo number_format(filesize($filename)).PHP_EOL;
		exec('xdg-open '.escapeshellarg($filename));
		echo "Delete?".PHP_EOL;
		if (fgets(STDIN)) {
			unlink($filename);
			echo "deleted".PHP_EOL;
		}
	}
} else if (isset($opts['m']) && isset($opts['n'])) {
	if (is_string($opts['n'])) $result = UnifiedArchive::archiveNodes(array($opts['n']), $filename, $fake);
	else $result = UnifiedArchive::archiveNodes($opts['n'], $filename, $fake);

	if ($fake) var_export($result);
	else {
		echo "result: ".var_export($result, true).PHP_EOL;
		echo "saved to ".$filename.PHP_EOL;
		echo number_format(filesize($filename)).PHP_EOL;
		exec('xdg-open '.escapeshellarg($filename));
		echo "Delete?".PHP_EOL;
		if (fgets(STDIN)) {
			unlink($filename);
			echo "deleted".PHP_EOL;
		}
	}
} else if (isset($opts['e'])) {
	$nodes = array(
		array('source' => '/etc/php5/fpm/php.ini', 'destination' => 'php.ini'),
		array('source' => '/home/wapmorgan/Pictures/*', 'destination' => 'Pictures/'),
		array('source' => '/home/wapmorgan/Pictures/Screenshots/*', 'destination' => 'Pictures/', 'recursive' => true),
		array('source' => '/home/wapmorgan/Dropbox/0203/s01v00_files/', 'destination' => 'lec/'),
		array('source' => '/home/wapmorgan/Dropbox/0203/s01v01_files/', 'destination' => 'lec/'),
		array('source' => '/home/wapmorgan/Dropbox/0203/s02v00_files/', 'destination' => 'lec/'),
		array('source' => '/home/wapmorgan/Dropbox/Notes/1/', 'destination' => 'Notes/', 'recursive' => true),
		array('source' => '/home/wapmorgan/Dropbox/Notes/2/', 'destination' => 'Notes/', 'recursive' => true),
	);
	$result = UnifiedArchive::archiveNodes($nodes, $filename, $fake);
	if ($fake) var_export($result);
	else {
		echo "result: ".var_export($result, true).PHP_EOL;
		echo "saved to ".$filename.PHP_EOL;
		echo number_format(filesize($filename)).PHP_EOL;
		exec('xdg-open '.escapeshellarg($filename));
		echo "Delete?".PHP_EOL;
		if (fgets(STDIN)) {
			unlink($filename);
			echo "deleted".PHP_EOL;
		}
	}
}
