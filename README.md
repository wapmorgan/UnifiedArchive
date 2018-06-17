UnifiedArchive - unified interface to all popular archive formats (zip # 7z #
rar # gz # bz2 # xz # cab # tar # tar.gz # tar.bz2 # tar.x # tar.Z # iso-9660)
for listing, reading, extracting and creation + built-in console archive
manager + PclZip-like interface for zip archives.

[![Composer package](http://composer.network/badge/wapmorgan/unified-archive)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Latest Stable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/stable)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Total Downloads](https://poser.pugx.org/wapmorgan/unified-archive/downloads)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Daily Downloads](https://poser.pugx.org/wapmorgan/unified-archive/d/daily)](https://packagist.org/packages/wapmorgan/unified-archive)
[![License](https://poser.pugx.org/wapmorgan/unified-archive/license)](https://packagist.org/packages/wapmorgan/unified-archive)

**Contents**:
---
1. [**Preamble**](#preamble)
2. [**Installation**](#installation)
3. [**Reading of archive**](#reading-of-archive)
    1. [**Archive modification**](#archive-modification)
    2. [**Archive creation**](#archive-creation)
4. [**Built-in console archive manager**](#built-in-console-archive-manager)
5. [**API**](#api)
    1. [**UnifiedArchive**](#unifiedarchive)
    2. [**ArchiveEntry**](#archiveentry)
6. [**PclZip-like interface**](#pclzip-like-interface)
7. [**Formats support**](#formats-support)
8. [**Changelog**](#changelog)

## Preamble
If on your site there is a possibility of uploading of archives and you would
like to add functionality of their automatic unpacking and viewing with no
dependency on format of the archive, you can use this library.

## Installation
Composer package: `wapmorgan/unified-archive`
[[1](https://packagist.org/packages/wapmorgan/unified-archive)]

```json
{
    "require": {
        "wapmorgan/unified-archive": "~0.1.0"
    }
}
```

## Reading of archive
1. Import a class

    ```php
    require 'vendor/autoload.php';
    use \wapmorgan\UnifiedArchive\UnifiedArchive;
    ```

2. At the beginning, try to open the file with automatic detection of a format
by name. In case of successful recognition a `UnifiedArchive` object will be
returned. In case of failure - **null**

    ```php
    $archive = UnifiedArchive::open('filename.rar');
    // or
    $archive = UnifiedArchive::open('filename.zip');
    // or
    $archive = UnifiedArchive::open('filename.7z');
    // or
    $archive = UnifiedArchive::open('filename.gz');
    // or
    $archive = UnifiedArchive::open('filename.bz2');
    // or
    $archive = UnifiedArchive::open('filename.xz');
    // or
    $archive = UnifiedArchive::open('filename.cab');
    // or
    $archive = UnifiedArchive::open('filename.tar');
    // or
    $archive = UnifiedArchive::open('filename.tar.gz');
    // or
    $archive = UnifiedArchive::open('filename.tar.bz2');
    // or
    $archive = UnifiedArchive::open('filename.tar.xz');
    // or
    $archive = UnifiedArchive::open('filename.tar.Z');
    // or
    $archive = UnifiedArchive::open('filename.iso');
    ```

3. Further, read the list of files of archive (note: this function returns
only names of files)

    ```php
    var_dump($archive->getFileNames()); // array with files list
    ```

4. Further, you can get additional information about concrete file by
`getFileData()` method

    ```php
    var_dump($archive->getFileData('README.md')); // ArchiveEntry with file information
    ```

5. Further, you can get raw file contents by `getFileContent()`
method

    ```php
    var_dump($archive->getFileContent('README.md')); // string
    ```

6. Further, you can unpack any internal catalog or the whole archive with files
on a disk. The `extractFiles()` method is engaged in it. In case of success, it
returns number of the extracted files, in case of failure - **false**. Initial
and final symbol of division of catalogs are very important! Don't forget them.

    ```php
    $archive->extractFiles($outputFolder, $archiveFiles);

    // to unpack all contents of archive
    $archive->extractFiles('output');

    // to unpack the src catalog in archive in the sources catalog on a disk
    $archive->extractFiles('output', '/src/');

    // to unpack the bookmarks catalog in archive in the sources catalog on a
    // disk
    $archive->extractFiles('output', '/bookmarks/');
    ```

### Archive modification
1. Deletion files from archive

    ```php
    // To delete a single file from an archive
    $archive->deleteFiles('README.md');

    // To delete multiple files from an archive
    $archive->deleteFiles(['README.md', 'MANIFEST.MF']);
    ```

    In case of success the number of successfully deleted files will be returned.

2. Addition files to archive

    ```php
    // To add completely the catalog with all attached files and subdirectories (all directory contents will be stored in archive root)
    $archive->addFiles('/var/log');

    // To add one file (will be stored as one file "syslog")
    $archive->addFiles('/var/log/syslog');

    // To add some files or catalogs (all catalogs structure in paths will be kept)
    $archive->addFiles([$directory, $file, $file2, ...]);
    ```

### Archive creation
To pack completely the catalog with all attached files and subdirectories
in new archive:

```php
UnifiedArchive::archiveFiles('/var/log', 'Archive.zip');

// To pack one file
UnifiedArchive::archiveFiles('/var/log/syslog', 'Archive.zip');

// To pack some files or catalogs
UnifiedArchive::archiveFiles([$directory, $file, $file2, ...], 'Archive.zip');
```

Extended syntax with possibility of paths rewriting:

```php
$nodes = [
    '/etc/php5/fpm/php.ini' => 'php.ini',
    '/media/pictures/other/cats' => 'Pictures', // all files from `cats` dir will be stored in Pictures
    '/media/pictures/Cats' => 'Pictures', // the same
    '/home/user/Desktop/catties' => 'Pictures',  // the same
    '/home/user/Dropbox/software/1' => 'SoftwareVersions', // all files will be stored in SoftwareVersions
    '/home/user/Dropbox/software/2' => 'SoftwareVersions', // the same
];
UnifiedArchive::archiveFiles($nodes, 'Archive.zip');
```

## Built-in console archive manager
UnifiedArchive is distributed with a unified console program to manipulate popular
archive formats. This script is stored in `vendor/bin/cam`.

It supports all formats that UnifiedArchive does and can be used to manipulate
archives without other software. To check your configuration and check formats
support launch it with `-f` flag in console:

```
$ php vendor/bin/cam -f
```

### Full usage help
```
USAGE: cam (-l|--list)  ARCHIVE
       cam (-t|--table) ARCHIVE
       cam (-i|--info)  ARCHIVE
       cam (-e|--extract) [--output=DIR] [--replace=(all|ask|none|time|size)] [--flat=(file|path)] [--exclude=PATTERN] ARCHIVE [FILES_IN_ARCHIVE...]
       cam (-p|--print)   ARCHIVE FILES_IN_ARCHIVE...
       cam (-d|--details) ARCHIVE FILES_IN_ARCHIVE...
       cam (-x|--delete)  ARCHIVE FILES_IN_ARCHIVE...
       cam (-a|--add)     ARCHIVE FILES_ON_DISK...
       cam (-c|--create)  ARCHIVE FILES_ON_DISK...
       cam (-f|--formats)

ACTIONS:
      -l(--list)    List files
      -t(--table)   List files in table
      -i(--info)    Summary about archive

      -e(--extract) Extract from archive

      -p(--print)   Print files' content
      -d(--details) Details about files
      -x(--delete)  Delete files

      -a(--add)     Add to archive
      -c(--create)  Create new archive

```

## API
### `UnifiedArchive`

| Method                                                                                             | Description                                                                                                            | When it fails                                                       |
|----------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| `UnifiedArchive::open($fileName): UnifiedArchive | null`                                           | Tries to detect type of archive and open it.                                                                           | Returns `null` when archive is not recognized or not supported.     |
| `getFileNames(): array`                                                                            | Returns list of files in archive.                                                                                      |                                                                     |
| `isFileExists($fileName): boolean`                                                                 | Checks whether file is stored in archive.                                                                              |                                                                     |
| `getFileData($fileName): ArchiveEntry|false`                                                       | Returns metadata of file in archive.                                                                                   | Returns `false` when file is not is archive.                        |
| `getFileContent($fileName): string|false`                                                          | Returns raw file content from archive.                                                                                 | Returns `false` when file is not is archive.                        |
| `getFileResource($fileName): resource|false`                                                       | Returns a resource that can be used to read all file contents from archive.                                            | Returns `false` when file is not is archive.                        |
| `countFiles(): integer`                                                                            | Returns number of files in archive.                                                                                    |                                                                     |
| `getArchiveSize(): integer`                                                                        | Returns size of archive in bytes.                                                                                      |                                                                     |
| `getArchiveType(): string`                                                                         | Returns type of archive (like zip/rar/etc).                                                                            |                                                                     |
| `countCompressedFilesSize(): integer`                                                              | Returns size of all compressed files from archive in bytes.Returns size of all compressed files from archive in bytes. |                                                                     |
| `countUncompressedFilesSize(): integer`                                                            | Returns size of all uncompressed files from archive in bytes.                                                          |                                                                     |
| `extractFiles($outputFolder, $node = '/'): integer`                                                | Extracts all files or few files from archive to specific directory.                                                    |                                                                     |
| `deleteFiles($fileOrFiles): integer|false`                                                         | Returns number of deleted files.                                                                                       | Returns `false` when archive does not support archive modification. |
| `addFiles($fileOrFiles): integer`                                                                  | Returns number of added files.                                                                                         | Returns `false` when archive does not support archive modification. |
| `UnifiedArchive::archiveFiles($fileOrFiles, $aname, $simulation = false): integer | array | false` | Creates a new archive with passed files list.                                                                          | Returns `false` when format does not support archive creation.      |

The main class representing an archive.

```php
static public function open($filename): UnifiedArchive | null
```
Tries to detect type of archive and open it. Returns a  `UnifiedArchive`
instance in case of success, `null` in case of failure.

```php
public function __construct($filename, $type)
```
Creation of object of a class with specific type.

```php
public function getFileNames(): array
```
Obtaining the list of files in archive. The symbol of division of catalogs
can be both a slash, and a backslash (depends on format).

```php
isFileExists($fileName): boolean
```
Checks that passed file is present in archive.

```php
public function getFileData($filename): ArchiveEntry
```
Returns detailed information on the file in archive. The name of the file
has to be the same as one stored in archive. It is restricted to
change a symbol of division of catalogs. This method returns object of
_wapmorgan\UnifiedArchive\ArchiveEntry_.

```php
public function getFileContent($filename): string
```
Receiving "crude" contents of the file. For text files it is the text,
for images/video/music are crude binary data. The file name besides has to be in
accuracy as in archive.

```php
public function getFileResource($filename): resource|false
```
Returns a resource that can be used to read all file contents
without full extraction. If file does not exist, false will be returned.

```php
public function countFiles(): integer
```
Counts total of all files in archive.

```php
public function getArchiveSize(): integer
```
Returns the archive size (the file size) in bytes.

```php
public function getArchiveType(): string
```
Receives archive type (like `rar` or `zip`).

```php
public function countCompressedFilesSize(): integer
```
Counts the size of all PACKED useful data in bytes (that is contents of all
files listed in archive).

```php
public function countUncompressedFilesSize(): integer
```
Counts the size of all UNPACKED useful data in bytes (that is contents of all
files listed in archive).

```php
public function extractFiles($outputFolder, $node = '/'): integer
```
Unpacks any of internal catalogs archive with full preservation of structure
of catalogs in the catalog on a hard disk. Returns number of extracted files.

```php
public function deleteFiles($fileOrFiles): integer
```
Updates existing archive by removing files from it. Returns number of deleted
files.

```php
public function addFiles($fileOrFiles): integer
```
Updates existing archive by adding new files. Returns total number of files
after addition.

```php
static public function archiveFiles($fileOrFiles, $aname, $simulation = false): integer | boolean | array
```
Archives files transferred in the first argument. Returns number of the
archived files in case of success, in case of failure - **false**.
If as the third argument is **true**, then the real archiving doesn't
happen, and the result contains the list of the files chosen for an
archiving, their number and total size.

### `ArchiveEntry`
The class representing a file from archive as result of a call to `getFileData()`.
It containts fields with file information:
* `string $path` - file name in archive.
* `boolean $isCompressed` - the boolean value, containing `true` if the file
was packed with compression.
* `integer $compressedSize` - the size of the PACKED contents of the file in
bytes. If no compression used, will have the same value with next field.
* `integer $uncompressedSize` - the size of the UNPACKED contents of the file
in bytes.
* `integer $modificationTime` - time of change of the file (the integer value
containing number
of seconds passed since the beginning of an era of Unix).

## PclZip-like interface
UnifedArchive provides full realization of the interface known by popular archiving
library "PclZip" (only for **zip** format, the last version 2.8.2).

Let's look at it:

```php
use wapmorgan\UnifiedArchive\UnifiedArchive;
require 'vendor/autoload.php';
$archive = UnifiedArchive::open('ziparchive.zip');
$pclzip = $archive->pclzipInteface();
```

You are from this point free to use all available methods provided by the class
PclZip:

1. `create()` - creation of new archive, packing of files and catalogs.
2. `listContent()` - receiving contents of archive.
3. `extract()` - unpacking of files and catalogs.
4. `properties()` - obtaining information on archive.
5. `add()` - addition of files in archive.
6. `delete()` - cleaning of archive of files.
7. `merge()` - "pasting" of two archives.
8. `duplicate()` - archive cloning.

All available options and the parameters accepted by original PclZip are also
available.

It is also important to note increase in productivity when using my version of
the PclZip-interface using a native class for work, over old and working with
"crude" contents of archive means of the PHP-interpreter.

*The PclZip-interface is at present in a stage of experimental realization. I
ask to take it into account.*

For those who isn't familiar with the PclZip interface or wishes to refresh
knowledge, visit official documentation on PclZip on the official site:
http://www.phpconcept.net/pclzip.

Also I need to note that one of an option nevertheless is unrealizable:
_PCLZIP_OPT_NO_COMPRESSION_. This option allows to disconnect compression for
added files. At present the native library for work *doesn't allow* to change
compression parameters from zip-archive - all added the file forcibly contract.
I tried to find a roundabout way, but at present to make it it didn't turn out.

**Performance comparation**

To confirm my words about boost that UnifiedArchive can make in your project,
here's comparation table of UnifiedArchive and PclZip extracting the same
archives.

| Filename            | UA (time) | % of PZ     | PZ (time) |
|---------------------|-----------|-------------|-----------|
| googletools.zip     | 0.014     | **67%**     | 0.020     |
| PHPWord-develop.zip | 0.573     | **63%**     | 0.907     |
| turbosale_1.0.0.zip | 0.250     | **80%**     | 0.309     |
| meteor-devel.zip    | 6.553     | **62%**     | 10.429    |
| subrion-develop.zip | 10.682    | **82%**     | 12.996    |
| OptiKey-master.zip  | 3.445     | **82%**     | 4.180     |

**Average growth is 27%!**

## Formats support

| Formats                                                     | Requirement                                                                                      | getFileContent()                              | getFileResource() | addFiles() / removeFiles() | archiveFiles() | Notes                                                                                                                              |
|-------------------------------------------------------------|--------------------------------------------------------------------------------------------------|-----------------------------------------------|-------------------|----------------------------|----------------|------------------------------------------------------------------------------------------------------------------------------------|
| .zip                                                        | `zip` extension                                                                                  | ✔                                             | ✔                 | ✔                          | ✔              |                                                                                                                                    |
| .7zip, .7z                                                  | [`gemorroj/archive7z`](https://packagist.org/packages/gemorroj/archive7z) package and `7zip-cli` | ✔                                             | ✔ (simulation)    | ✔                          | ✔              |                                                                                                                                    |
| .tar, .tar.gz, .tar.bz2, .tar.xz, .tar.Z, .tgz, .tbz2, .txz | [`pear/archive_tar`](https://packagist.org/packages/pear/archive_tar) package                    | ✔                                             | ✔ (simulation)    | ❌                          | ✔              | Compressed versions of tar are supported by appropriate libraries or extenions (zlib, bzip2, xz) or installed software (ncompress) |
| .tar, .tar.gz, .tar.bz2, .tgz, .tbz2                        | `phar` extension                                                                                 | ✔                                             | ✔ (simulation)    | ✔                          | ✔              | Compressed versions of tar are supported by appropriate libraries or extenions (zlib, bzip2)                                       |
| .rar                                                        | `rar` extension                                                                                  | ✔                                             | ✔                 | ❌                          | ❌              |                                                                                                                                    |
| .iso                                                        | [`phpclasses/php-iso-file`](https://packagist.org/packages/phpclasses/php-iso-file) package      | ✔                                             | ✔ (simulation)    | ❌                          | ❌              |                                                                                                                                    |
| .cab                                                        | [`wapmorgan/cab-archive`](https://packagist.org/packages/wapmorgan/cab-archive) package          | ✔ (extraction ability depends on PHP version) | ✔ (simulation)    | ❌                          | ❌              | Extraction is supported only on PHP 7.0.22+, 7.1.8+, 7.2.0.                                                                        |
| .gz                                                         | `zlib` extension                                                                                 | ✔                                             | ✔                 |                            |                |                                                                                                                                    |
| .bz2                                                        | `bzip2` extension                                                                                | ✔                                             | ✔                 |                            |                |                                                                                                                                    |
| .xz                                                         | `lzma2` extension                                                                                | ✔                                             | ✔                 |                            |                |                                                                                                                                    |

## Changelog

| Version | Date         | Changelog                                                                                                                                                                                |
|---------|--------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 0.1.0   | Apr 11, 2018 | * Renamed methods `extractNode()` -> `extractFiles()`, `archiveNodes()` -> `archiveFiles()`. * Added checks for archive format. * Changed `getFileData()` output.                                  |
| 0.0.11  | Mar 21, 2018 | * Cleaned up some old code. * Added `ext-phar` adapter for `tar` archives (if `pear/archive_tar` is not installed).                                                                           |
| 0.0.10  | Aug 7, 2017  | * Remove `docopt` from requirements.                                                                                                                                                       |
| 0.0.9   | Jul 20, 2017 | * Added `cam` script.                                                                                                                                                                      |
| 0.0.8   | Jan 24, 2017 | * Added initial support for CAB archives without extracting. * Added handling of short names of tar archives. * Removed external repository declaration. * Removed die() in source code. |
| 0.0.7   | Jan 14, 2017 | * Fixed using ereg function on PHP >7.                                                                                                                                                   |
| 0.0.6   | Jan 9, 2017  | * Added functionality for adding files in archive. * Added functionality for deleting files from archive. * Fixed discovering 7z archive number of files and creating new archive.       |
| 0.0.5   | Jan 8, 2017  | * Added support for `7z` (7zip) archives.                                                                                                                                                  |
| 0.0.4   | Jan 7, 2017  | * Added support for single-file `bz2` (bzip2) and `xz` (lzma2) archives.                                                                                                                     |
| 0.0.3   | Aug 18, 2015 | * Removed archive_tar from required packages.                                                                                                                                            |
| 0.0.2   | May 27, 2014 | * Released under the MIT license                                                                                                                                                         |
| 0.0.1   | May 26, 2014 | ---                                                                                                                                                                                      |
