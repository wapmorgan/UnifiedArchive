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
4. [**Formats support**](#formats-support)
5. [**API**](#api)
    1. [**UnifiedArchive**](#unifiedarchive)
    2. [**ArchiveEntry**](#archiveentry)
    3. [**PclZip-like interface**](#pclzip-like-interface)
6. [**Built-in console archive manager**](#built-in-console-archive-manager)
7. [**Changelog**](#changelog)

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
    
4. Further, check that specific file is in archive.
    
    ```php
    var_dump($archive->isFileExists('README.md')); // boolean
    ```

5. Further, you can get additional information about concrete file by
`getFileData()` method

    ```php
    var_dump($archive->getFileData('README.md')); // ArchiveEntry with file information
    ```

6. Further, you can get raw file contents by `getFileContent()`
method

    ```php
    var_dump($archive->getFileContent('README.md')); // string
    ```

7. Further, you can unpack any internal catalog or the whole archive with files
on a disk. The `extractFiles()` method is engaged in it. In case of success, it
returns number of the extracted files, in case of failure - **false**. Initial
and final symbol of division of catalogs are very important! Don't forget them.

    ```php
    $archive->extractFiles($outputFolder, $archiveFiles);

    // to unpack all contents of archive
    $archive->extractFiles('output');
 
    // to unpack specific files from archive
    $archive->extractFiles('output', ['README.md', 'composer.json']);

    // to unpack the src catalog in archive in the sources catalog on a disk
    $archive->extractFiles('output', '/src/', true);

    // to unpack the bookmarks catalog in archive in the sources catalog on a
    // disk
    $archive->extractFiles('output', '/bookmarks/', true);
    ```

### Archive modification
1. Deletion files from archive

    ```php
    // To delete a single file from an archive
    $archive->deleteFiles('README.md');

    // To delete multiple files from an archive
    $archive->deleteFiles(['README.md', 'MANIFEST.MF']);
     
    // To delete directories from archive
    $archive->deleteFiles('/src/', true);
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
| .xz                                                         | [`lzma2` extension](https://github.com/payden/php-xz)                                                                                | ✔                                             | ✔                 |                            |                |                                                                                                                                    |

## API
### `UnifiedArchive`

### Static Methods

| Method                                                                                             | Description                                                                                                            | When it fails                                                       |
|----------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| `UnifiedArchive::open($fileName): UnifiedArchive`                                                  | Tries to detect type of archive and open it.                                                                           | Returns `null` when archive is not recognized or not supported.     |
| `UnifiedArchive::canOpenArchive($fileName): boolean)`                                              | Checks whether archive can be opened.                                                                                  |                                                                     |
| `UnifiedArchive::canOpenType($type): boolean`                                                      | Checks whether archive format can be opened.                                                                           |                                                                     |
| `UnifiedArchive::archiveFiles($fileOrFiles, $archiveName, $simulation = false): integer/array`     | Creates a new archive and stores passed files list into it.                                                            | Returns `false` when format does not support archive creation.      |
| `UnifiedArchive::archiveFile($file, $archiveName, $simulation = false): integer/array`             | Creates a new archive with one file.                                                                                   | Returns `false` when format does not support archive creation.      |
| `UnifiedArchive::archiveDirectory($fileOrFiles, $archiveName, $simulation = false): integer/array` | Creates a new archive and stores content of passed directory into it.                                                  | Returns `false` when format does not support archive creation.      |

### Object methods

- Archive information methods:

| Method                                                                                   | Description                                                                                                            |
|------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|
| `countFiles(): integer`                                                                  | Returns number of files in archive.                                                                                    |
| `getArchiveType(): string`                                                               | Returns type of archive (like zip/rar/etc).                                                                            |
| `getArchiveSize(): integer`                                                              | Returns size of archive in bytes.                                                                                      |
| `countCompressedFilesSize(): integer`                                                    | Returns size of all compressed files from archive in bytes.                                                            |
| `countUncompressedFilesSize(): integer`                                                  | Returns size of all uncompressed files from archive in bytes.                                                          |

- Archive manipulation methods:

| Method                                                                                   | Description                                                                                                            | When it fails                                                       |
|------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| `getFileNames(): array`                                                                  | Returns list of files in archive.                                                                                      |                                                                     |
| `isFileExists($fileName): boolean`                                                       | Checks whether file with specific name is in archive.                                                                  |                                                                     |
| `getFileData($fileName): ArchiveEntry`                                                   | Returns an `ArchiveEntry` instance with metadata of file in archive (size, compressed size and modification date).     | Returns `false` when file is not in archive.                        |
| `getFileContent($fileName): string`                                                      | Returns raw file content from archive.                                                                                 | Returns `false` when file is not in archive.                        |
| `getFileResource($fileName): resource`                                                   | Returns a `resource` that can be used to read all file contents from archive.                                          | Returns `false` when file is not in archive.                        |

- Archive modification methods:

| Method                                                                                   | Description                                                                                                            | When it fails                                                       |
|------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| `extractFiles($outputFolder, $node = '/', $expandFilesList = false): integer`            | Extracts all files or few files from archive to specific directory and returns number of extracted files.              | Returns `false` when some error occurred.                           |
| `deleteFiles($fileOrFiles, $expandFilesList = false): integer`                           | Deletes files from archive and returns number of deleted files.                                                        | Returns `false` when archive does not support archive modification. |
| `addFiles($fileOrFiles): integer`                                                        | Adds files to archive and returns number of added files.                                                               | Returns `false` when archive does not support archive modification. |
| `addFile($file, $inArchiveName = null): boolean`                                         | Adds one file to archive.                                                                                              | Returns `false` when archive does not support archive modification. |
| `addDirectory($directory, $inArchivePath = null): boolean`                               | Adds files from directory to archive.                                                                                  | Returns `false` when archive does not support archive modification. |

**Notes**:

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
It contains fields with file information:
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

### PclZip-like interface
UnifiedArchive provides for zip archives full realization of the interface 
known by popular archiving library "PclZip" (the last version 2.8.2).

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

**Performance comparision**

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

## Changelog

To see all changes in library go to [CHANGELOG file](CHANGELOG.md).
