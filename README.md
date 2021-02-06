UnifiedArchive - unified interface to all popular archive formats (zip # 7z #
rar # gz # bz2 # xz # cab # tar # tar.gz # tar.bz2 # tar.x # tar.Z # iso) for
listing, reading, extracting and creation + built-in console archive manager.

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
3. [**Formats support**](#formats-support)
4. [**Installation**](#installation)
5. [**Usage**](#usage)
6. [**API**](#api)
7. [**Built-in console archive manager**](#built-in-console-archive-manager)
8. [**Changelog**](#changelog)

## Preamble
If on your site/service there is a possibility of usage archives of many types, and you would
like to work with them unified, you can use this library.

## Functions
- Opening an archive with automatic format detection
- Opening archives encrypted with password (zip, rar, 7z)
- Getting information about uncompressed size of archive contents
- Listing archive content
- Getting details (\[un\]compressed size, date of modification) of every archived file
- Extracting archived file content as is or on a disk
- Reading archived file content as stream (zip, rar)
- Adding files to archive
- Removing files from archive
- Creating new archives with files/directories
- Adjust compression level (zip, gzip)

## Simple usage

```php
$archive = \wapmorgan\UnifiedArchive\UnifiedArchive::open('archive.zip');
$extracted_size = $archive->countUncompressedFilesSize();
$files_list = $archive->getFileNames();

echo 'Files list: '.array_map(function ($file) { return '- '.$file."\n"; }, $files_list).PHP_EOL;
echo 'Total size after extraction: '.$extracted_size.' byte(s)';
```

## Formats support

More information in [Drivers document](docs/Drivers.md).

## Installation
Composer package: [`wapmorgan/unified-archive`](https://packagist.org/packages/wapmorgan/unified-archive)

- Add to composer.json
```json
{
    "require": {
        "wapmorgan/unified-archive": "^1.0.0"
    }
}
```

Or run `composer require wapmorgan/unified-archive` from your main package root folder.

## Usage

Complex usage example of UnifiedArchive of described in [Usage document](docs/Usage.md).

## API

Full API of UnifiedArchive is described in [API document](docs/API.md).

## Changelog

To see all changes in library go to [CHANGELOG file](CHANGELOG.md).

## Built-in console archive manager
UnifiedArchive is distributed with a unified console program to manipulate popular
archive formats. This script is stored in `vendor/bin/cam`.

It supports all formats that UnifiedArchive does and can be used to manipulate
archives without other software. To check your configuration and check formats
support launch it with `-f` flag in console:

```
$ php vendor/bin/cam -f
```
