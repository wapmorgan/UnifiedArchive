UnifiedArchive - an archive manager with a unified way of working with all popular archive formats (zip # 7z # 
rar # gz # bz2 # xz # cab # tar # tar.gz # tar.bz2 # tar.x # tar.Z # ...) for PHP with ability for
listing, reading, extracting and creation + built-in console archive manager.

[![Latest Stable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/stable)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Total Downloads](https://poser.pugx.org/wapmorgan/unified-archive/downloads)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Daily Downloads](https://poser.pugx.org/wapmorgan/unified-archive/d/daily)](https://packagist.org/packages/wapmorgan/unified-archive)
[![License](https://poser.pugx.org/wapmorgan/unified-archive/license)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Latest Unstable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/unstable)](https://packagist.org/packages/wapmorgan/unified-archive)

Tests & Quality: [![Build status](https://travis-ci.org/wapmorgan/UnifiedArchive.svg?branch=master)](https://travis-ci.org/wapmorgan/UnifiedArchive)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/?branch=0.1.x)
[![Code Coverage](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/?branch=0.1.x)

## Goal
If on your site/service there is a possibility of usage archives of many types, and you would
like to work with them unified, you can use this library.

UnifiedArchive can utilize to handle as many formats as possible:
* ZipArchive, RarArchive, PharData
* Pear/Tar
* 7zip cli program via Gemorroj/Archive7z
* zip, tar cli programs via Alchemy/Zippy
* ext-zlib, ext-bz2, ext-xz

## Functions & Features
- Opening an archive with automatic format detection
- Opening archives encrypted with password (zip, rar, 7z)
- Getting information about uncompressed size of archive contents
- Listing archive content
- Getting details (\[un\]compressed size, date of modification) of every archived file
- Reading archived file content as stream (zip, rar, gz, bz2, xz)
- Extracting archived file content as is or on a disk
- Appending an archive with new files
- Removing files from archive
- Creating new archives with files/directories, adjust compression level (zip, gzip), set passwords (7z, zip)

## Quick start
```sh
composer require wapmorgan/unified-archive
# install libraries for support: tar.gz, tar.bz2, zip
composer require pear/archive_tar alchemy/zippy
# or if you can, install p7zip package in your OS and SevenZip driver for support a lot of formats (tar.*, zip, rar)
composer require gemorroj/archive7z
# to work with rar natively
pecl install rar
```
More information about formats support in [formats page](docs/Drivers.md).

Use it in code:
```php
$archive = \wapmorgan\UnifiedArchive\UnifiedArchive::open('archive.zip'); // archive.rar, archive.tar.bz2
$extracted_size = $archive->countUncompressedFilesSize();
$files_list = $archive->getFileNames();

echo 'Files list: '.array_map(function ($file) { return '- '.$file."\n"; }, $files_list).PHP_EOL;
echo 'Total size after extraction: '.$extracted_size.' byte(s)';
```

## Built-in console archive manager
UnifiedArchive is distributed with a unified console program to manipulate archives.
It supports all formats that UnifiedArchive does and can be used to manipulate
archives without other software. To show help, launch it:
```
./vendor/bin/cam --help
```

## Details

1. [Drivers and their formats](docs/Drivers.md).
2. [Usage with examples](docs/Usage.md).
3. [Full API description](docs/API.md).
4. [Changelog](CHANGELOG.md).
