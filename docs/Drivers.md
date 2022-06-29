Here is a list of available drivers with their differences.

|           Driver          | Zip       | Rar       | TarByPhar            | TarByPear                           | SevenZip                      | AlchemyZippy              | Iso          | Cab     |
|:-------------------------:|-----------|-----------|----------------------|-------------------------------------|-------------------------------|---------------------------|--------------|---------|
|          formats          | zip       | rar       | tar, tar-gz, tar-bz2 | tar, tar-gz, tar-bz2, tar-Z, tar-xz | 7z, zip, rar, tar, iso,  ...  | zip, tar, tar-gz, tar-bz2 | iso          | cab     |
|            type           | **extension** | **extension** | **extensions**       | _library_ + **extensions**            | **library + OS utility**          | **library + OS utilities**   |  **library** | **library** |
| Open with password        | ✔         | ✔         |                      |                                     | ✔                             |                                    |              |
| Get comment               | ✔         | ✔         |                      |                                     |                               |                                    |              |
| Update comment            | ✔         |           |                      |                                     |                               |                                    |              |
|        Stream files       | ✔         | ✔         |                      |                                     |                               |                                    |              |
|         Add files         | ✔         |           | ✔                    | ✔                                   | ✔                             | ✔                                  |              |
|        Delete files       | ✔         |           | ✔                    |                                     | ✔                             | ✔                                  |              |
|           Create          | ✔         |           | ✔                    | ✔                                   | ✔                             | ✔                                  |              |
| Specify compression level | ✔         |           |                      |                                     | ✔                             |                                    |              |
| Encrypt with password     | ✔         |           |                      |                                     | ✔                             |                                    |              |

Example for `zip` with two drivers support: If on your system both `Zip` and `AlchemyZippy` driver available, the first will be used for `zip` file.

# Proposals for installation drivers
**In docker/on VDS:** Install extensions (`zip, rar, phar, zlib, bzip2`), program (`p7zip`) and SevenZip driver.

**In common hosting case:** Install TarByPear, AlchemyZippy libraries.

# Drivers
## Extensions

| Driver | php extension | formats  | notes |
|--------|---------------|----------|-------|
| Zip | `zip`         | zip, jar |       |
| Rar | `rar` | rar      | read-only mode |
| TarByPhar | `phar` | zip, tar, tar.gz, tar.bz2 | Has a bug [#71966](https://bugs.php.net/bug.php?id=71966&thanks=10) in `ext-phar` ([related issue](https://github.com/wapmorgan/UnifiedArchive/issues/12)) - an archive will `./` in paths cannot be opened. |

## Utilities + bridge

**SevenZip**
- Type: library ([`gemorroj/archive7z`](https://packagist.org/packages/gemorroj/archive7z)) and console program p7zip ([`7z`](http://p7zip.sourceforge.net/))
- Formats: 7z, XZ, BZIP2, GZIP, TAR, ZIP, WIM, AR, ARJ, CAB, CHM, CPIO, CramFS, DMG, EXT, FAT, GPT, HFS, IHEX, ISO, LZH, LZMA, MBR, MSI, NSIS, NTFS, QCOW2, RAR, RPM, SquashFS, UDF, UEFI, VDI, VHD, VMDK, WIM, XAR and Z
- Works via command line (= should be available `proc_*` functions).
- Can open and create archives with passwords (only ZIP format).
- Can adjust new archives compression level.

**AlchemyZippy**
- Type: library ([`alchemy/zippy`](https://packagist.org/packages/alchemy/zippy)) and console programs:
    - `tar`
    - `zip`
- Formats: zip, tar, tar.bz2, tar.gz
- Works via command line (= should be available `proc_*` functions).
- All functions

**If you install SevenZip and AlchemyZippy**:
1. You should specify symfony/console version before installation to any **3.x.x version**: `composer require symfony/process:~3.4`, because they require different `symfony/process` versions.
2. Install archive7z version 4.0.0: `composer require gemorroj/archive7z:~4.0`

## Pure php implementation
Works with binary data in php-land (= eats a lot of memory for big archives).

| Driver | formats                             | library                                                                               | notes                                                                                                                   |
|--------|-------------------------------------|---------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|
| TarByPear | tar, tar.gz, tar.bz2, tar.xz, tar.Z | [`pear/archive_tar`](https://packagist.org/packages/pear/archive_tar)) and optionally php-extensions (`zlib`, `bzip2`, `xz` - [5.x](https://github.com/payden/php-xz) / [7.x](https://github.com/codemasher/php-ext-xz) | dont support updating archive (deleteFiles)                                                                             |
| Cab    | cab                                 | [`wapmorgan/cab-archive`](https://packagist.org/packages/wapmorgan/cab-archive) | Getting files content and extraction is supported only on PHP 7.0.22+, 7.1.8+, 7.2.0. Support only opening & extraction |
| Iso    | iso                                 | [`phpclasses/php-iso-file`](https://packagist.org/packages/phpclasses/php-iso-file) | Support only opening & extraction                                                                                       |

### Only compression
These drivers support only compressed (not archived) data.  They support opening, extracting&streaming and creation.

| Compression | PHP extension                                                                                    | Formats        |
|-------------|--------------------------------------------------------------------------------------------------|----------------|
| bz2         | `bzip2`                                                                                          | .bz2, .tar.bz2 |
| gzip        | `zlib`                                                                                           | .gz, .tar.gz   |
| lzma        | `xz` - [5.x](https://github.com/payden/php-xz) / [7.x](https://github.com/codemasher/php-ext-xz) | .xz, .tar.xz   |
