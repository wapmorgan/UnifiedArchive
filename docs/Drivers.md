- [Proposals for installation drivers](#proposals-for-installation-drivers)
- [Drivers](#drivers)
  - [PHP Extensions](#php-extensions)
  - [Utilities + bridge](#utilities--bridge)
  - [Pure PHP](#pure-php-implementation)
- [Full support matrix](#full-support-matrix)

| Type | Pros | Cons                                                                                               | Useful for                                                              |
|------|------|----------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------|
| PHP Exteniosn | Fast, supports a lot of functions | Sometimes can not support specific functions (e.g. password-protection in zip on old php versions) | Use it when possible (when extensions installed)                        |
| Utilities + bridge | Uses system utilities, so should be fast (and even faster PHP Extensions) | Do not support streaming                                                                           | Packing a lot of files / Unpacking the whole archives without streaming |
| Pure PHP | Works without PHP Extensions or system utilities, can be installed via composer only | Uses a lot of memory, lack of speed                                                                | Fallback method                                                         |

# Proposals for installation drivers
- **In common case:**
  - install `TarByPear`, `NelexaZip`
  - If installed `7za` utility - configure `SevenZip` driver
  - Else if installed `tar` or `unzip` utilities - configure `AlchemyZippy` driver
- **In docker/on VDS:**
  - install all php extensions (`zip, rar, phar, zlib, bz2`)
  - install utility (`7za` - `p7zip-full` on ubuntu) and `SevenZip` driver

# Drivers
## PHP extensions

| Driver | Formats                   | php extension | notes                                                                                                                                                                                                        |
|--------|---------------------------|---------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Zip | zip, jar                  | `zip`         | supports password-protection since 7.2.0                                                                                                                                                                     |
| Rar | rar                       | `rar` | read-only mode                                                                                                                                                                                               |
| TarByPhar | zip, tar, tar.gz, tar.bz2 | `phar` | Has a bug [#71966](https://bugs.php.net/bug.php?id=71966&thanks=10) in `ext-phar` ([related issue](https://github.com/wapmorgan/UnifiedArchive/issues/12)) - an archive will `./` in paths cannot be opened. |

### PHP extensions for compression only

These drivers support only compressed (not archived) data.  They support opening, extracting&streaming and creation.

| Driver | Formats | PHP extension                                                                                    |
|--------|---------|--------------------------------------------------------------------------------------------------|
| Bzip   | .bz2    | `bzip2`                                                                                          |
| Gzip   | .gz     | `zlib`                                                                                           |
| Lzma   | .xz     | `xz` - [5.x](https://github.com/payden/php-xz) / [7.x](https://github.com/codemasher/php-ext-xz) |

## Utilities + bridge

| Driver | Formats                                                                                                                                                                                                    | utility + bridge                                                                                                           |
|--------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| SevenZip | 7z, xz, bzip2, gzip, tar, zip, wim, ar, arj, cab, chm, cpio, cramfs, dmg, ext, fat, gpt, hfs, ihex, iso, lzh, lzma, mbr, msi, nsis, ntfs, qcow2, rar, rpm, squashfs, udf, uefi, vdi, vhd, vmdk, wim, xar, z | p7zip ([`7za`](http://p7zip.sourceforge.net/)) + [`gemorroj/archive7z`](https://packagist.org/packages/gemorroj/archive7z) |
| AlchemyZippy | zip, tar, tar.gz, tar.bz2                                                                                                                                                                                  | `zip`/`tar` + [`alchemy/zippy`](https://packagist.org/packages/alchemy/zippy)                                              |

**If you install SevenZip and AlchemyZippy at the same time**:
1. You should specify symfony/console version before installation to any **3.x.x version**: `composer require symfony/process:~3.4`, because they require different `symfony/process` versions.
2. Install archive7z version 4.0.0: `composer require gemorroj/archive7z:~4.0`

## Pure php implementation

Works with binary data in php-land (= eats a lot of memory for big archives).

| Driver | Formats                             | library                                                                                                                                                                                                                     | notes                                                                                                                   |
|--------|-------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|
| NelexaZip | zip                                 | [`nelexa/zip`](https://packagist.org/packages/nelexa/zip) and optionally php-extensions (`bz2`, `fileinfo`, `iconv`, `openssl`) |                                                                              |
| TarByPear | tar, tar.gz, tar.bz2, tar.xz, tar.Z | [`pear/archive_tar`](https://packagist.org/packages/pear/archive_tar) and optionally php-extensions (`zlib`, `bzip2`, `xz` - [5.x](https://github.com/payden/php-xz) / [7.x](https://github.com/codemasher/php-ext-xz)      | dont support updating archive (deleteFiles)                                                                             |
| Cab    | cab                                 | [`wapmorgan/cab-archive`](https://packagist.org/packages/wapmorgan/cab-archive)                                                                                                                                             | Getting files content and extraction is supported only on PHP 7.0.22+, 7.1.8+, 7.2.0. Support only opening & extraction |
| Iso    | iso                                 | [`phpclasses/php-iso-file`](https://packagist.org/packages/phpclasses/php-iso-file)                                                                                                                                         | Support only opening & extraction                                                                                       |

# Full support matrix

Result of `./vendor/bin/cam system:formats` when all drivers installed and configured (except of AlchemyZippy):
```
+-----------------+------------+------+------+------+-------+--------+--------+--------+-----------+-----+-----+-----+------+-----+-----+-----+-----+-----+-----+-----+------+-------+
| driver / format | zip        | rar  | gz   | bz2  | xz    | tar    | tgz    | tbz2   | 7z        | cab | iso | arj | uefi | gpt | mbr | msi | dmg | rpm | deb | udf | txz  | tar.z |
+-----------------+------------+------+------+------+-------+--------+--------+--------+-----------+-----+-----+-----+------+-----+-----+-----+-----+-----+-----+-----+------+-------+
| Zip             | oOtxsadTcC |      |      |      |       |        |        |        |           |     |     |     |      |     |     |     |     |     |     |     |      |       |
| Rar             |            | oOxs |      |      |       |        |        |        |           |     |     |     |      |     |     |     |     |     |     |     |      |       |
| Gzip            |            |      | oxsc |      |       |        |        |        |           |     |     |     |      |     |     |     |     |     |     |     |      |       |
| Bzip            |            |      |      | oxsc |       |        |        |        |           |     |     |     |      |     |     |     |     |     |     |     |      |       |
| Lzma            |            |      |      |      | oxsc  |        |        |        |           |     |     |     |      |     |     |     |     |     |     |     |      |       |
| TarByPhar       | oxsadc     |      |      |      |       | oxsadc | oxsadc | oxsadc |           |     |     |     |      |     |     |     |     |     |     |     |      |       |
| SevenZip        | oOxadcC    |      |      |      | oxadc | oxadc  |        |        | oOtxadTcC | ox  | ox  | ox  | ox   | ox  | ox  | ox  | ox  | ox  | ox  | ox  |      |       |
| AlchemyZippy    |            |      |      |      |       |        |        |        |           |     |     |     |      |     |     |     |     |     |     |     |      |       |
| NelexaZip       | oOxad      |      |      |      |       |        |        |        |           |     |     |     |      |     |     |     |     |     |     |     |      |       |
| TarByPear       |            |      |      |      |       | oxac   | oxac   | oxac   |           |     |     |     |      |     |     |     |     |     |     |     | oxac |       |
| Iso             |            |      |      |      |       |        |        |        |           |     | ox  |     |      |     |     |     |     |     |     |     |      |       |
| Cab             |            |      |      |      |       |        |        |        |           | ox  |     |     |      |     |     |     |     |     |     |     |      |       |
+-----------------+------------+------+------+------+-------+--------+--------+--------+-----------+-----+-----+-----+------+-----+-----+-----+-----+-----+-----+-----+------+-------+
```

- o - open
- O - open (+password)
- t - get comment
- x - extract
- s - stream
- a - append
- d - delete
- T - set comment
- c - create
- C - create (+password)
