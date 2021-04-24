*UnifiedArchive* - an archive manager with a unified way for different formats. 
Supports all basic (listing, reading, extracting and creation) and specific features (compression level, password-protection). 
Bundled with console program for working with archives.

Supported formats (depends on installed drivers): zip, 7z, rar, one-file(gz, bz2, xz), tar (tar.gz, tar.bz2, tar.x, tar.Z), and a lot of others. 

[![Latest Stable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/stable)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Total Downloads](https://poser.pugx.org/wapmorgan/unified-archive/downloads)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Daily Downloads](https://poser.pugx.org/wapmorgan/unified-archive/d/daily)](https://packagist.org/packages/wapmorgan/unified-archive)
[![License](https://poser.pugx.org/wapmorgan/unified-archive/license)](https://packagist.org/packages/wapmorgan/unified-archive)
[![Latest Unstable Version](https://poser.pugx.org/wapmorgan/unified-archive/v/unstable)](https://packagist.org/packages/wapmorgan/unified-archive)

Tests & Quality: [![Build status](https://travis-ci.com/wapmorgan/UnifiedArchive.svg?branch=master)](https://travis-ci.com/github/wapmorgan/UnifiedArchive)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/wapmorgan/UnifiedArchive/?branch=master)

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
- Open an archive with automatic format detection (more 20 formats)
- Open archives encrypted with password (zip, rar, 7z)
- List archive content, calculate original size of archive, read (zip, rar) & set (zip) archive comment
- Get details (original size, date of modification) of every archived file. Read archived file content as stream (zip, rar, gz, bz2, xz). Extract archived file content as is or on a disk
- Extract all archive content
- Append an archive with new files or directories
- Remove files from archive
- Creat new archives with files/directories
- Adjust compression level (zip, gzip, 7zip) for new archives
- Set passwords (7z, zip) for new archives

## Quick start
```sh
composer require wapmorgan/unified-archive
# install php libraries for support: tar.gz, tar.bz2, zip
composer require pear/archive_tar alchemy/zippy
# if you can, install `p7zip` package in OS and `SevenZip` driver
sudo apt-get install p7zip-full && composer require gemorroj/archive7z
# install ext-rar for native work
pecl install rar

# Check supported formats
./vendor/bin/cam --formats
```
More information about formats support in [formats page](docs/Drivers.md).

Use it in code:
```php
# Extraction
$archive = \wapmorgan\UnifiedArchive\UnifiedArchive::open('archive.zip'); // archive.rar, archive.tar.bz2

if ($archive !== null) {
    $output_dir = '/var/www/extracted';
    if (disk_free_space($output_dir) > $archive->getOriginalSize()) {
        $archive->extractFiles($output_dir);
        echo 'Extracted files list: '.implode(', ', $archive->getFileNames()).PHP_EOL;
    }
}

# Archiving
\wapmorgan\UnifiedArchive\UnifiedArchive::archiveFiles([
    'README.md' => '/default/path/to/README.md',
    'content' => '/folder/with/content/',
], 'archive.zip', \wapmorgan\UnifiedArchive\Drivers\BasicDriver::COMPRESSION_MAXIMUM);
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
