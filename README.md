UnifiedArchive - unified interface to all popular archive formats (zip # 7z #
rar # gz # bz2 # xz # cab # tar # tar.gz # tar.bz2 # tar.x # tar.Z # iso-9660)
for listing, reading, extracting and creation + built-in console archive
manager.

[![Composer package](http://composer.network/badge/wapmorgan/unified-archive)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Latest Stable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/stable)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Total Downloads](https://poser.pugx.org/wapmorgan/unified-archive/downloads)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Daily Downloads](https://poser.pugx.org/wapmorgan/unified-archive/d/daily)](https://packagist.org/packages/wapmorgan/unified-archive)
[![License](https://poser.pugx.org/wapmorgan/unified-archive/license)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Latest Unstable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/unstable)](https://packagist.org/packages/wapmorgan/unified-archive)

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
        "wapmorgan/unified-archive": "~0.1.1"
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

Also, there is extended syntax for `addFiles()` and `archiveFiles()`:

```php
UnifiedArchive::archiveFiles([
          '/var/www/site/abc.log' => 'abc.log',   // stored as 'abc.log'
          '/var/www/site/abc.log',                // stored as '/var/www/site/abc.log'
          '/var/www/site/runtime/logs' => 'logs', // directory content stored in 'logs' dir
          '/var/www/site/runtime/logs',           // stored as '/var/www/site/runtime/logs'
    ], 'archive.zip');
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

- Archive opening:

| Method                                                                                             | Description                                                                                                            | When it fails                                                       |
|----------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| `UnifiedArchive/null UnifiedArchive::open($fileName) `                                             | Tries to detect type of archive and open it.                                                                           | Returns `null` when archive is not recognized or not supported.     |
| `boolean UnifiedArchive::canOpenArchive($fileName)`                                                | Checks whether archive can be opened.                                                                                  |                                                                     |
| `boolean UnifiedArchive::canOpenType($type)`                                                       | Checks whether archive format can be opened.                                                                           |                                                                     |

- Archive creation. These methods create archive with one file, directory contents or with custom files list.

| Method                                                                                             | Description                                                                                                            | When it fails                                                       |
|----------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| `boolean UnifiedArchive::archiveFile($file, $archiveName)`                                         | Creates a new archive with one file.                                                                                   | Returns `false` when format does not support archive creation.      |
| `boolean UnifiedArchive::archiveDirectory($directory, $archiveName)`                               | Creates a new archive and stores content of passed directory into it.                                                  | Returns `false` when format does not support archive creation.      |
| `integer/array UnifiedArchive::archiveFiles($fileOrFiles, $archiveName, $simulation = false)`      | Creates a new archive and stores passed files list into it.                                                            | Returns `false` when format does not support archive creation.      |

### Object methods

- Archive information methods:

| Method                                                                                   | Description                                                                                                            |
|------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|
| `integer countFiles()`                                                                    | Returns number of files in archive.                                                                                    |
| `string getArchiveType()`                                                                 | Returns type of archive (like zip/rar/etc).                                                                            |
| `integer getArchiveSize()`                                                                | Returns size of archive in bytes.                                                                                      |
| `integer countCompressedFilesSize()`                                                      | Returns size of all compressed files from archive in bytes.                                                            |
| `integer countUncompressedFilesSize()`                                                    | Returns size of all uncompressed files from archive in bytes.                                                          |

- Archive content information methods:

| Method                                                                                   | Description                                                                                                            | When it fails                                                       |
|------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| `array getFileNames()`                                                                   | Returns list of files in archive.                                                                                      |                                                                     |
| `boolean isFileExists($fileName)`                                                        | Checks whether file with specific name is in archive.                                                                  |                                                                     |
| `ArchiveEntry getFileData($fileName)`                                                    | Returns an `ArchiveEntry` instance with metadata of file in archive (size, compressed size and modification date).     | Returns `false` when file is not in archive.                        |
| `string getFileContent($fileName)`                                                       | Returns raw file content from archive.                                                                                 | Returns `false` when file is not in archive.                        |
| `resource getFileResource($fileName)`                                                    | Returns a `resource` that can be used to read all file contents from archive.                                          | Returns `false` when file is not in archive.                        |
| `integer extractFiles($outputFolder, $node = '/', $expandFilesList = false)`             | Extracts all files or few files from archive to specific directory and returns number of extracted files.              | Returns `false` when some error occurred.                           |

- Archive modification methods:

| Method                                                                                   | Description                                                                                                            | When it fails                                                       |
|------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| `integer deleteFiles($fileOrFiles, $expandFilesList = false)`                            | Deletes files from archive and returns number of deleted files.                                                        | Returns `false` when archive does not support archive modification. |
| `boolean addFile($file, $inArchiveName = null)`                                          | Adds one file to archive.                                                                                              | Returns `false` when archive does not support archive modification. |
| `boolean addDirectory($directory, $inArchivePath = null)`                                | Adds files from directory to archive.                                                                                  | Returns `false` when archive does not support archive modification. |
| `integer addFiles($fileOrFiles)`                                                         | Adds files to archive and returns number of added files.                                                               | Returns `false` when archive does not support archive modification. |

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

| Property                    | Description                                                                                                               |
|-----------------------------|---------------------------------------------------------------------------------------------------------------------------|
| `string $path`              | File name in archive.                                                                                                     |
| `boolean $isCompressed`     | Boolean value, containing `true` if the file was stored with compression.                                                 |
| `integer $compressedSize`   | Size of the PACKED contents of the file in bytes. If no compression used, will have the same value with next field.       |
| `integer $uncompressedSize` | Size of the original unpacked contents of the file in bytes.                                                              |
| `integer $modificationTime` | Time of change of the file (the integer value containing number of seconds passed since the beginning of an era of Unix). |

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
