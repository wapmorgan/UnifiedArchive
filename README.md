UnifiedArchive - unified interface to archive (zip`/`rar`/`gz`/`tar`/`tar.gz`/`tar.bz2`/`iso-9660) for listing, reading, extracting and creation + built-in console packer and unpacker + fully implementated PclZip-like interface (create, listContent, extract, properties, add, delete, merge, duplicate)..

**Contents**:
---
1. **Preamble**
2. **Installation**
3. **Process of reading archive**
	1. **Process of creation of archive**
		1. Restrictions
4. **Built-in archiver**
5. **API**
	1. **Object methods**
	2. **Static methods**
6. **PclZip-like interface**
7. **Examples**

## Preamble
If on your site there is a possibility of uploading of archives and you would like to add functionality of their automatic unpacking and viewing with no dependency on format of the archive, you can use this library.

## Installation
Composer package: `wapmorgan/unified-archive` [[1](https://packagist.org/packages/wapmorgan/unified-archive)]

	{
		"require": {
				"wapmorgan/unified-archive": "dev-master"
		}
	}

## Process of reading archive
0. Import a class
	```php
	require 'vendor/autoload.php';
	use \wapmorgan\UnifiedArchive\UnifiedArchive;
	```

1. At the beginning, try to open the file with automatic detection of a format by name. In case of successful recognition the object of UnifiedArchive will be returned. in case of failure - null
	```php
	$archive = UnifiedArchive::open('filename.rar');
	// or
	$archive = UnifiedArchive::open('filename.zip');
	// or
	$archive = UnifiedArchive::open('filename.gz');
	// or
	$archive = UnifiedArchive::open('filename.tar');
	// or
	$archive = UnifiedArchive::open('filename.tar.gz');
	// or
	$archive = UnifiedArchive::open('filename.tar.bz2');
	// or
	$archive = UnifiedArchive::open('filename.iso');
	```

2. Further, read the list of files of archive (notice, this function returns only names of files)
	```php
	var_dump($archive->getFileNames());
	```

3. Further, you can receive additional information on the concrete file by means of the getFileData function
	```php
	var_dump($archive->getFileData('README.md'));
	```

4. Further, you can receive file contents by means of the getFileContent function ()
	```php
	var_dump($archive->getFileContent('README.md'));
	```

5. Further you can unpack any internal catalog with files on a disk. Including all archive (the root catalog of archive). The extractNode method is engaged in it. In case of success, it returns number of the mentioned files, in case of failure - false. Initial and final symbol of division of catalogs are very important! Don't forget them.
	```php
	$archive->extractNode(outputFolder, archiveFolder = '/');
	// to unpack all contents of archive
	$archive->extractNode(tmpnam('/tmp', 'arc'));
	// to unpack the src catalog in archive in the sources catalog on a disk
	$archive->extractNode(tmpnam('/tmp', 'sources'), '/src/');
	// to unpack the bookmarks catalog in archive in the sources catalog on a disk
	$archive->extractNode(tmpnam('/tmp', 'sources'), '/bookmarks/');
	```

## Process of creation of archive
To pack completely the catalog with all attached files and subdirectories:
```php
UnifiedArchive::archiveNodes('/var/log', 'Archive.zip');
```
To pack one file:
```php
UnifiedArchive::archiveNodes('/var/log/syslog', 'Archive.zip');
```
To pack some files or catalogs:
```php
UnifiedArchive::archiveNodes(array(directory, file1, file2, ...), 'Archive.zip');
```

Extended syntax with possibility of rewriting of paths and additional opportunities:
```php
$nodes = array(
	array('source' => '/etc/php5/fpm/php.ini', 'destination' => 'php.ini'),
	array('source' => '/home/.../Dropbox/software/1/', 'destination' => 'SoftwareVersions/', 'recursive' => true),
	array('source' => '/home/.../Dropbox/software/2/', 'destination' => 'SoftwareVersions/', 'recursive' => true),
	array('source' => 'pictures/other/cats/*', 'destination' => 'Pictures/'),
	array('source' => '~/Desktop/catties/*', 'destination' => 'Pictures/'),
	array('source' => '/media/wapmorgan/.../Cats/*', 'destination' => 'Pictures/'),
);
UnifiedArchive::archiveNodes($nodes, 'Archive.zip');
```

[**Complete description of expanded syntax with examples and explanations: Full `archiveNodes` documentation**](doc.archiveNodes.md).

### Restrictions
It is impossible to create the ideal archiver, here therefore some restrictions and technical features are listed further:

1. Creation of rar of archives is impossible. proprietary format
2. If the `source` doesn't come to an end on "/"" or "*", it means there has to be a file.
3. If the `source` is a directory, destination too means has to be a directory (to terminate on "/").

## Built-in archiver
To see all opportunities of this remarkable UnifiedArchive, together with source codes some scripts are delivered. They can become quite good replacement to standard commands of file system (tar, unzip, rar, gzip).
```bash
DIRECTORY=/tmp/output
# /* All couples of commands are completely identical */
# /* tar replacement */
tar xv -C $DIRECTORY archive.tar.gz
./cli.hierarchy.php -e -n / -a archive.tar.gz -o $DIRECTORY

# /* unzip replacement */
unzip archive.zip -d $DIRECTORY
./cli.hierarchy.php -e -n / -a archive.zip -o $DIRECTORY

# /* unrar replacement */
unrar x archive.rar $DIRECTORY
./cli.hierarchy.php -e -n / -a archive.rar -o $DIRECTORY

# /* gzip -d replacement */
gzip -d -k archive.gz && mv archive $DIRECTORY
./cli.hierarchy.php -e -n / -a archive.gz -o $DIRECTORY
```
You noticed? The universal extractor itself defines type of archive and there is no need manually to choose type.

We will continue and look at examples of replacement of the unbridled number of utilities on one command:
```bash
# List of nodes in archive
./cli.hierarchy.php -l -a archivename
# Table of nodes in archive
./cli.hierarchy.php -t -a archivename
# Hierarchy of nodes in archive
./cli.hierarchy.php -h -a archivename

# /* unzip -v archivename file_in_archive replacement */
./cli.hierarchy.php -d -a archivename -n file_in_archive
# /* unzip archivename file_in_archive replacement */
./cli.hierarchy.php -e -a archivename -n /file_in_archive -o .
# /* unzip -p archivename file_in_archive replacement */
./cli.hierarchy.php -u -a archivename -n file_in_archive
# /* unzip archivename "directory_in_archive/*" replacement */
./cli.hierarchy.php -e -a archivename -n /directory_in_archive/ -o .

# /* Archive information */
./cli.hierarchy.php -i -a archivename
```
In the future probably I will add still some scripts or I will update existing having added some operations.

It is very easy and equally universal for any of supported types of archives (which already about 5)!

Try, it will be pleasant to you!

It is possible even to use the console version of the archiver in daily work for viewing of contents of archive from the terminal!

Conveniently, isn't that so?

## API
Create object of class `\wapmorgan\UnifiedArchive\UnifiedArchive` implementing the `\wapmorgan\UnifiedArchive\AbstractArchive` interface which has the following methods:

### Object methods

```php
public function __construct($filename, $type);
```
- creation of object of a class.

```php
public function getFileNames();
```
- obtaining the list of files in archive. The symbol of division of catalogs can be both a slash, and a backslash.

```php
public function getFileData($filename);
```
- obtaining detailed information on the file in archive. The name of the file has to COINCIDE in ACCURACY with what is registered in archive. It is restricted to add gaps or other symbols, to change a symbol of division of catalogs. This method returns object of **stdClass** which has following fields:
	* `filename` - file name in archive.
	* `compressed_size` - the size of the PACKED contents of the file.
	* `uncompressed_size` - the size of the UNPACKED contents of the file.
	* `mtime` - time of change of the file (the integer value containing number of seconds, passed since the beginning of an era of Unix).
	* `is_compressed` - the Boolean value, containing "truth" if the file was packed with compression.

```php
public function getFileContent($filename);
```
- receiving "crude" contents of the file. For text files it also is the text, for images/video/music are crude binary data. The file name besides has to be in accuracy as in archive.

```php
public function countFiles();
```
- counts total of all files in archive.

```php
public function getArchiveSize();
```
- counts the archive size (the file size).

```php
public function getArchiveType();
```
- receives archive type.

```php
public function countCompressedFilesSize();
```
- counts the size of all PACKED useful data (that is contents of all files listed in archive).

```php
public function countUncompressedFilesSize();
```
- counts the size of all UNPACKED useful data (that is contents of all files listed in archive).

```php
public function extractNode($outputFolder, $node = '/');
```
- unpacks any of internal catalogs archive with full preservation of structure of catalogs in the catalog on a hard disk.

### Static methods

```php
static public function open($filename);
```
- tries to distinguish type of archive and creates an instance UnifiedArchive in case of success, returns null in case of failure.

```php
static public function archiveNodes($nodes, $aname);
```
- archives notes transferred in the first argument. Returns number of the archived files in case of success, in case of failure - false.
If as the third argument (yes, real signature is `static public function archiveNodes(array $nodes, $aname, $fake = false)`) "truth" is transferred, then the real archiving doesn't occur, and the result contains the list of the files chosen for an archiving, their number and total size.

## PclZip-like interface
Yes, you didn't mishear - UnifedArchive provides full realization of the interface known on archiving popular library of PclZip (the last version 2.8.2).

Let's look at it:
```php
<?php
use wapmorgan\UnifiedArchive\UnifiedArchive;
require 'vendor/autoload.php';
$archive = UnifiedArchive::open('ziparchive.zip');
$pclzip = $archive->pclzipInteface();
```

You are from this point free to use all available methods provided by the class PclZip:

1. `create()` - creation of new archive, packing of files and catalogs.
2. `listContent()` - receiving contents of archive.
3. `extract()` - unpacking of files and catalogs.
4. `properties()` - obtaining information on archive.
5. `add()` - addition of files in archive.
6. `delete()` - cleaning of archive of files.
7. `merge()` - "pasting" of two archives.
8. `duplicate()` - archive cloning.

All available options and the parameters accepted by original PclZip are also available.

It is also important to note increase in productivity when using my version of the PclZip-interface using a native class for work, over old and working with "crude" contents of archive means of the PHP-interpreter.

*The PclZip-interface is at present in a stage of experimental realization. I ask to take it into account.*

For those who isn't familiar with the PclZip interface or wishes to refresh knowledge, visit official documentation on PclZip on the official site: http://www.phpconcept.net/pclzip.

Also I need to note that one of an option nevertheless is unrealizable: PCLZIP_OPT_NO_COMPRESSION. This option allows to disconnect compression for added files. At present the native library for work *doesn't allow* to change compression parameters from zip-archive - all added the file forcibly contract. I tried to find a roundabout way, but at present to make it it didn't turn out.

## Examples
In the **examples** catalog there are some files for check of operability of the program. start them from a command line.

* It is necessary to transfer a catalog name in `cli.fs.php` for scanning.
* It is necessary to transfer a path to archive in `cli.hierachy.php` for unpacking.
