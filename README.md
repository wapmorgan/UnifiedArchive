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
- Getting information about uncompressed size of archive contents
- Listing archive content
- Getting details (\[un\]compressed size, date of modification) of every archived file
- Extracting archived file content as is or on a disk
- Reading archived file content as stream
- Adding files to archive
- Removing files from archive
- Creating new archives with files/directories

## Formats support

| Formats                                                     | Driver                                                                                            | getFileContent() / getFileResource() | addFiles() / removeFiles() | archiveFiles() | Notes                                                                                                                              |
|-------------------------------------------------------------|---------------------------------------------------------------------------------------------------|--------------------------------------|----------------------------|----------------|------------------------------------------------------------------------------------------------------------------------------------|
| .zip                                                        | extension: `zip`                                                                                  | ✔                                    | ✔                          | ✔              |                                                                                                                                    |
| .7zip, .7z                                                  | package: [`gemorroj/archive7z`](https://packagist.org/packages/gemorroj/archive7z) AND `7zip-cli` | ✔ / ✔ \[1\]                          | ✔                          | ✔              | Uses system binary `7z` to work                                                                                                    |
| .tar, .tar.gz, .tar.bz2, .tar.xz, .tar.Z, .tgz, .tbz2, .txz | package: [`pear/archive_tar`](https://packagist.org/packages/pear/archive_tar)                    | ✔ / ✔ \[1\]                          | ❌                          | ✔              | Compressed versions of tar are supported by appropriate libraries or extenions (zlib, bzip2, xz) or installed software (ncompress) |
| .tar, .tar.gz, .tar.bz2, .tgz, .tbz2                        | extension: `phar`                                                                                 | ✔ / ✔ \[1\]                          | ✔                          | ✔              | Compressed versions of tar are supported by appropriate libraries or extenions (zlib, bzip2)                                       |
| .rar                                                        | extension: `rar`                                                                                  | ✔                                    | ❌                          | ❌              |                                                                                                                                    |
| .iso                                                        | package: [`phpclasses/php-iso-file`](https://packagist.org/packages/phpclasses/php-iso-file)      | ✔ / ✔ \[1\]                          | ❌                          | ❌              |                                                                                                                                    |
| .cab                                                        | package: [`wapmorgan/cab-archive`](https://packagist.org/packages/wapmorgan/cab-archive)          | ✔\[2\] / ✔ \[1\]\[2\]                | ❌                          | ❌              | Extraction is supported only on PHP 7.0.22+, 7.1.8+, 7.2.0.                                                                        |
| .gz                                                         | extension: `zlib`                                                                                 | ✔                                    |                            | ✔              |                                                                                                                                    |
| .bz2                                                        | extension: `bzip2`                                                                                | ✔                                    |                            | ✔              |                                                                                                                                    |
| .xz                                                         | extension: [`lzma2`](https://github.com/payden/php-xz)                                            | ✔                                    |                            | ✔              |                                                                                                                                    |

- \[1\] Simulation mode
- \[2\] Extraction ability depends on PHP version


## Installation
Composer package: `wapmorgan/unified-archive`
[[1](https://packagist.org/packages/wapmorgan/unified-archive)]

- Add these lines to composer.json
```json
{
    "require": {
        "wapmorgan/unified-archive": "^1.0.0"
    }
}
```

- Or run `composer require wapmorgan/unified-archive` from your main package root folder.

## Usage

Complex usage example of UnifiedArchive of described in [Usage section](docs/Usage.md).

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
