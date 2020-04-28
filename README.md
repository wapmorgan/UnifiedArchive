UnifiedArchive - unified interface to all popular archive formats (zip # 7z #
rar # gz # bz2 # xz # cab # tar # tar.gz # tar.bz2 # tar.x # tar.Z # iso-9660)
for listing, reading, extracting and creation + built-in console archive
manager.

[![Latest Stable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/stable)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Total Downloads](https://poser.pugx.org/wapmorgan/unified-archive/downloads)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Daily Downloads](https://poser.pugx.org/wapmorgan/unified-archive/d/daily)](https://packagist.org/packages/wapmorgan/unified-archive)
[![License](https://poser.pugx.org/wapmorgan/unified-archive/license)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Latest Unstable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/unstable)](https://packagist.org/packages/wapmorgan/unified-archive)

Tests & Quality: [![Build status](https://travis-ci.org/wapmorgan/UnifiedArchive.svg?branch=0.1.x)](https://travis-ci.org/wapmorgan/UnifiedArchive)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/badges/quality-score.png?b=0.1.x)](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/?branch=0.1.x)
[![Code Coverage](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/badges/coverage.png?b=0.1.x)](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/?branch=0.1.x)

**Contents**:
---
1. [**Preamble**](#preamble)
2. [**Functions**](#functions)
3. [**Installation**](#installation)
4. [**Usage**](#usage)
    1. [**Archive modification**](#archive-modification)
    2. [**Archive creation**](#archive-creation)
5. [**Formats support**](#formats-support)
6. [**API**](#api)
7. [**Built-in console archive manager**](#built-in-console-archive-manager)
8. [**Changelog**](#changelog)

## Preamble
If on your site there is a possibility of uploading of archives and you would
like to add functionality of their automatic unpacking and viewing with no
dependency on format of the archive, you can use this library.

## Functions
- Opening an archive with automatic format detection
- Getting information about uncompressed size of archive contents
- Listing archive content
- Getting details (\[un\]compressed size, date of modification) of every archived file
- Extracting archived file content as is or on a disk
- Reading archived file content as stream
- Adding files to archive
- Removing files from archive
- Creating new archives with files/directories

## Installation
Composer package: `wapmorgan/unified-archive`
[[1](https://packagist.org/packages/wapmorgan/unified-archive)]

- Add these lines to composer.json
```json
{
    "require": {
        "wapmorgan/unified-archive": "~0.2.0"
    }
}
```

- Or run `composer require wapmorgan/unified-archive` from your main package root folder.

## Usage
1. Import `UnifiedArchive`

    ```php
    require 'vendor/autoload.php';
    use \wapmorgan\UnifiedArchive\UnifiedArchive;
    ```

2. At the beginning, try to open the file with automatic detection of a format
by name. In case of successful recognition an `UnifiedArchive` object will be
returned. In case of failure - _null_ will be returned.

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

3. Further, read the list of files of archive.

    ```php
    $files_list = $archive->getFileNames(); // array with files list
   // ['file', 'file2', 'file3', ...]
    ```

4. Further, check that specific file is in archive.

    ```php
    if ($archive->isFileExists('README.md')) {
       // some operations
    }
    ```

5. To get common information about specific file use `getFileData()` method.
This method returns [an `ArchiveEntry` instance](docs/API.md#ArchiveEntry)

    ```php
    $file_data = $archive->getFileData('README.md')); // ArchiveEntry with file information
    ```

6. To get raw file contents use `getFileContent()` method

    ```php
    $file_content = $archive->getFileContent('README.md')); // string
    // raw file content
    ```

7. Further, you can unpack all archive or specific files on a disk. The `extractFiles()` method is intended to it.

    ```php
    $archive->extractFiles(string $outputFolder, string|array $archiveFiles);
    ```

    _Example:_
    ```php
    // to unpack all contents of archive to "output" folder
    $archive->extractFiles(__DIR__.'/output');

    // to unpack specific files (README.md and composer.json) from archive to "output" folder
    $archive->extractFiles(__DIR__.'/output', ['README.md', 'composer.json']);

    // to unpack the "src" catalog with all content from archive into the "sources" catalog on a disk
    $archive->extractFiles(__DIR__.'/output', '/src/', true);
    ```

### Archive modification
Only few archive formats support modification:
- zip
- 7z
- tar (depends on low-level driver for tar - see Formats section for details)

For details go to [Formats support](#Formats-support) section.

1. Deletion files from archive

    ```php
    // Delete a single file
    $archive->deleteFiles('README.md');

    // Delete multiple files
    $archive->deleteFiles(['README.md', 'MANIFEST.MF']);

    // Delete directory with full content
    $archive->deleteFiles('/src/', true);
    ```

    In case of success the number of successfully deleted files will be returned.

    [Details](docs/API.md#UnifiedArchive--deleteFiles).

2. Addition files to archive

    ```php
    // Add a catalog with all contents with full paths
    $archive->addFiles('/var/log');

    // To add one file (will be stored as one file "syslog")
    $archive->addFiles('/var/log/syslog');

    // To add some files or catalogs (all catalogs structure in paths will be kept)
    $archive->addFiles([$directory, $file, $file2, ...]);
    ```

   [Details](docs/API.md#UnifiedArchive--addFiles).

### Archive creation
Only few archive formats support modification:
- zip
- 7z
- tar (with restrictions)

For details go to [Formats support](#Formats-support) section.

To pack completely the catalog with all attached files and subdirectories in new archive:

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

[Details](docs/API.md#UnifiedArchive--archiveFiles).

## Formats support

| Formats                                                     | Requirement                                                                                       | getFileContent() / getFileResource() |  addFiles() / removeFiles() | archiveFiles() | Notes                                                                                                                              |
|-------------------------------------------------------------|---------------------------------------------------------------------------------------------------|--------------------------------------|--------------------------- -|----------------|------------------------------------------------------------------------------------------------------------------------------------|
| .zip                                                        | extension: `zip`                                                                                  | ✔                                    | ✔                           | ✔              |                                                                                                                                    |
| .7zip, .7z                                                  | package: [`gemorroj/archive7z`](https://packagist.org/packages/gemorroj/archive7z) AND `7zip-cli` | ✔ / ✔ \[1\]                          | ✔                           | ✔              |                                                                                                                                    |
| .tar, .tar.gz, .tar.bz2, .tar.xz, .tar.Z, .tgz, .tbz2, .txz | package: [`pear/archive_tar`](https://packagist.org/packages/pear/archive_tar)                    | ✔ / ✔ \[1\]                          | ❌                           | ✔              | Compressed versions of tar are supported by appropriate libraries or extenions (zlib, bzip2, xz) or installed software (ncompress) |
| .tar, .tar.gz, .tar.bz2, .tgz, .tbz2                        | extension: `phar`                                                                                 | ✔ / ✔ \[1\]                          | ✔                           | ✔              | Compressed versions of tar are supported by appropriate libraries or extenions (zlib, bzip2)                                       |
| .rar                                                        | extension: `rar`                                                                                  | ✔                                    | ❌                           | ❌              |                                                                                                                                    |
| .iso                                                        | package: [`phpclasses/php-iso-file`](https://packagist.org/packages/phpclasses/php-iso-file)      | ✔ / ✔ \[1\]                          | ❌                           | ❌              |                                                                                                                                    |
| .cab                                                        | package: [`wapmorgan/cab-archive`](https://packagist.org/packages/wapmorgan/cab-archive)          | ✔\[2\] / ✔ \[2\]                     | ❌                           | ❌              | Extraction is supported only on PHP 7.0.22+, 7.1.8+, 7.2.0.                                                                        |
| .gz                                                         | extension: `zlib`                                                                                 | ✔                                    |                             | ✔              |                                                                                                                                    |
| .bz2                                                        | extension: `bzip2`                                                                                | ✔                                    |                             | ✔              |                                                                                                                                    |
| .xz                                                         | extension: [`lzma2`](https://github.com/payden/php-xz)                                            | ✔                                    |                             | ✔              |                                                                                                                                    |

- \[1\] Simulation mode
- \[2\] Extraction ability depends on PHP version

## API

API of UnifiedArchive is described in [API document](docs/API.md).

## Built-in console archive manager
UnifiedArchive is distributed with a unified console program to manipulate popular
archive formats. This script is stored in `vendor/bin/cam`.

It supports all formats that UnifiedArchive does and can be used to manipulate
archives without other software. To check your configuration and check formats
support launch it with `-f` flag in console:

```
$ php vendor/bin/cam -f
```

## Changelog

To see all changes in library go to [CHANGELOG file](CHANGELOG.md).
