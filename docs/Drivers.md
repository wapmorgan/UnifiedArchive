Here is a list of available drivers with description in priority of selection:

| Driver       | Memory-effective streaming            | appending | updating | encrypt | creation |
|--------------|-------------------------------------- |-----------|----------|---------|----------|
| Zip          | ✔                                     | ✔         | ✔        | ✔    | ✔          |
| Rar          | ✔                                     |           |          | ✔    |           |
| TarByPhar    |                                       | ✔         | ✔        |     | ✔           |
| TarByPear    |                                       |           |          |     | ✔          |
| SevenZip     |                                       | ✔         | ✔        | ✔    | ✔          |
| AlchemyZippy |                                       | ✔         | ✔        |     | ✔          |
| Iso          |                                       |           |          |     |            |
| Cab          |                                       |           |          |     |            |
| Gzip         | ✔                                     |           |          |     | ✔            |
| Bzip2        | ✔                                     |           |          |     | ✔            |
| Lzma2        | ✔                                     |           |          |     | ✔            |

If on your system both `Zip` and `AlchemyZippy` driver available, the first will be used for `zip` file.

## Zip
- Type: php-extension (`zip`)
- Formats: zip

## Rar
- Type: php-extension (`rar`)
- Formats: rar

## TarByPhar
- Type: php-extensions (`phar`) and optionally php-extensions (`zlib`, `bzip2`)
- Formats: tar, tar.gz, tar.bz2

## TarByPear
- Type: library ([`pear/archive_tar`](https://packagist.org/packages/pear/archive_tar)) and optionally php-extensions (`zlib`, `bzip2`)
- Formats: tar, tar.gz, tar.bz2, tar.xz, tar.Z
- Works with binary data in php-land.

## SevenZip
- Type: library ([`gemorroj/archive7z`](https://packagist.org/packages/gemorroj/archive7z)) and console program p7zip ([`7z`](http://p7zip.sourceforge.net/))
- Formats: 7z, XZ, BZIP2, GZIP, TAR, ZIP, WIM, AR, ARJ, CAB, CHM, CPIO, CramFS, DMG, EXT, FAT, GPT, HFS, IHEX, ISO, LZH, LZMA, MBR, MSI, NSIS, NTFS, QCOW2, RAR, RPM, SquashFS, UDF, UEFI, VDI, VHD, VMDK, WIM, XAR and Z
- Works via command line

## AlchemyZippy
- Type: library ([`alchemy/zippy`](https://packagist.org/packages/alchemy/zippy)) and console programs:
    - `tar`
    - `zip`
- Formats: zip, tar, tar.bz2, tar.gz
- Works via command line

## Cab
- Type: library ([`wapmorgan/cab-archive`](https://packagist.org/packages/wapmorgan/cab-archive))
- Formats: cab
- Works with binary data in php-land.
- Extraction is supported only on PHP 7.0.22+, 7.1.8+, 7.2.0.

## Iso
- Type: library ([`phpclasses/php-iso-file`](https://packagist.org/packages/phpclasses/php-iso-file))
- Formats: iso
- Works with binary data in php-land.

## One-file formats
These drivers support only compressed (not archived) data.

### Bzip2
- Type: php-extension (`bzip2`)
- Formats: bz2

### Gzip
- Type: php-extension (`zlib`)
- Formats: gz

### Lzma2
- Type: php-extension ([`xz`](https://github.com/payden/php-xz))
- Formats: xz
