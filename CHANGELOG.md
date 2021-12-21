# Change Log

### 1.1.4 - Dec 21, 2021
Disabled `rar` for SevenZip driver.

### 1.1.3 - May 2, 2021

**Changed format of `$files` in `archiveFiles()` and `addFiles()`**
```php
[
    '/var/www/log.txt',                // will be "/var/www/log.txt"
    'log2.txt' => '/var/www/log2.txt', // will be "/log2.txt"
    '/var/www/site',                   // will be "/var/www/site"
    'site2' => '/var/www/site2',       // will be "/site2"
]
```

Old format also works, but there can be a bad case. If you have */var/www/log2.txt* and *log2.txt* (in current directory) and pass following:
```php
[
'/var/www/log2.txt' => 'log2.txt',
]
```
it will archive *log2.txt* as */var/www/log2.txt* in an archive (new behaviour).

**New features**:
- Added `Formats::canStream()` to check if an archive files can be streamed.
- Added ability to create archives, encrypted with password (only *zip* (`Zip`, `SevenZip`) and *7z* (`SevenZip`)) - added nullable `$password` argument to:
  - `UnifiedArchive::archiveFiles($fileOrFiles, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE, $password = null)`
  - `UnifiedArchive::archiveFile($file, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE, $password = null)`
  - `UnifiedArchive::archiveDirectory($directory, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE, $password = null)`
- Added `UnifiedArchive->getMimeType()` to get mime type of archive.
- Added `UnifiedArchive->getComment()` to get comment of an archive. Available only in `Zip` and `Rar` drivers, others return `null`.
- Added `UnifiedArchive->setComment(?string $comment)` to set comment. Available only in `Zip`.
- Added filter in `UnifiedArcihve->getFileNames()`. If works as `fnmatch()` does.
- Added ability to iterate over archive and access files data as array:
```php
$a = \wapmorgan\UnifiedArchive\UnifiedArchive::open('tests/archives/fixtures.7z');
foreach ($a as $file => $data) {
    echo $file.PHP_EOL;
 }

$file_data = $a['filename'];
```

**Fixed:**
- Fixed `SevenZip` driver: disabled _tar.gz, tar.bzip2_ support as it isn't supported properly and described which formats driver can create, append, modify and encrypt.

**Methods renamed** (old exist, but marked as deprecated):
- `UnifiedArchive->getArchiveFormat` -> `UnifiedArchive->getFormat`.
- `UnifiedArchive->getArchiveSize` -> `UnifiedArchive->getSize`.
- `UnifiedArchive->countCompressedFilesSize` -> `UnifiedArchive->getCompressedSize`.
- `UnifiedArchive->countUncompressedFilesSize` -> `UnifiedArchive->getOriginalSize`.
- `UnifiedArchive->getFileResource` -> `UnifiedArchive->getFileStream`.
- `UnifiedArchive->isFileExists` -> `UnifiedArchive->hasFile`.

### 1.1.2 - Mar 1, 2021
Fixed calculation of tar's uncompressed size opened via `TarByPear` driver.
Fixed working with *tar.xz* archives.

### 1.1.1 - Feb 13, 2021
Cleaned package.

### 1.1.0 - Feb 13, 2021
**New features**:
- Added ability to open archives encrypted with password - added `$password` argument to `UnifiedArchive::open($fileName, $password = null)`. Works only with: zip, rar, 7z.   
- Added ability to adjust compression level for new archives - added `$compressionLevel` argument (with default `BasicDriver::COMPRESSION_AVERAGE` level) to:
    - `UnifiedArchive::archiveFiles($fileOrFiles, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE)`
    - `UnifiedArchive::archiveFile($file, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE)`
    - `UnifiedArchive::archiveDirectory($file, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE)`
  Works only with: zip, gzip.
- Added ability to append the archive with a file from string - added `addFileFromString` method:
  `UnifiedArchive->addFileFromString(string $inArchiveName, string $content)`.
- Added tests for format support:
    * `Formats::canOpen()`
    * `Formats::canCreate()`
    * `Formats::canAppend()` - check if file can be added to an archive
    * `Formats::canUpdate()` - check if archive member can be removed
    * `Formats::canEncrypt()` - check if encrypted archive can be opened

**Format changes**:
- Extended *SevenZip* driver: now it supports a lot of formats (7z, zip, rar, iso, tar and so on).
- Added *AlchemyZippy* driver: it works via command-line programs for zip, tar, tar.gz and tar.bz2.

**Methods renamed:**
- `UnifiedArchive::canOpenType` -> `Formats::canOpen`
- `UnifiedArchive::canOpenArchive` -> `UnifiedArchive::canOpen`
- `UnifiedArchive::canCreateType` -> `Formats::canCreate`
- `UnifiedArchive->getArchiveType` -> `UnifiedArchive->getArchiveFormat`

Old methods exist, but marked as deprecated.

### 1.0.1 - Nov 28, 2020

- Improved extendable for all classes - used late-static binding everywhere.

Format specific:
- **gzip**: improved detection of archive by content.

### 1.0.0 - Jun 13, 2020

Format specific:
- **tar**:
   - Fixed automatic opening of .tar.Z and .tar.xz.
- **rar**:
   - Exclude directories from files list.

### 0.2.0 - Feb 2, 2020

**BC-breaking changes**:
- **Deleted deprecated UnifiedArchive methods**: `extractNode`, `archiveNodes`.
- Functionality of preparing files list for archiving is moved from `archiveFiles()` to `prepareForArchiving()`.
- **All mutable methods throws exceptions on errors now**:
    * `getFileData`, `getFileContent`, `getFileResource` throws `NonExistentArchiveFileException` when file is not present in archive.
    * `extractFiles` throws:
        - `EmptyFileListException`
        - `ArchiveExtractionException`
    * `deleteFiles`, `addFiles`, `addFile` and `addDirectory` throws:
        - `EmptyFileListException`
        - `UnsupportedOperationException`
        - `ArchiveModificationException`
    *  `archiveFiles`, `archiveFile` and `archiveDirectory` throws:
        - `FileAlreadyExistsException`
        - `EmptyFileListException`
        - `UnsupportedOperationException`
        - `ArchiveCreationException`

### 0.1.3 - Jan 13, 2020

**BC-breaking changes**:
- **Minimal version is 5.5.0**.

Format specific:
- **zip**: Fixed PclZip-interface
- **7zip**: Fixed 7z-archiving, when archiving files should be renamed in archive
- **lzw**: Fixed check for availability (#15)

New features:
- Added `canCreateType(): bool`
- Added `canAddFiles(): bool`
- Added `canDeleteFiles(): bool`

### 0.1.2 - Jan 03, 2019

**BC-breaking changes**:
- PclZip-interface getter renamed to `getPclZipInterface()`.
- Make `addFiles()` return number of **added files** instead of total files number.

Other changes:
- Make `addFiles()` / `deleteFiles()` / `archiveFiles()` throw `\Exception`s when any error occurred (and even when
files list is empty).
- Fixed usage of `/` always as directory separator in `addFiles()` and `archiveFiles()`.

Format-specific changes:
- Divided format-specific code into separate components.
- **zip**:
    - Excluded directories from files list (`getFileNames()`).
    - Fixed retrieving new list of files after `addFiles()` usage.
    - (#11) Fixed invalid "/" archive entry after `archiveFiles()` usage.
- **tar** (`TarArchive` adapter):
    - Fixed number of added files of `addFiles()`.
    - Fixed list of files after `deleteFiles()` usage.
    - Added checks for compressed tar's support in `canOpenArchive()` and `canOpenType()`.
- **tar** (`PharData` adapter):
    - Fixed list of files after `addFiles()`/`deleteFiles()` usage and path generation of archive in `archiveFiles()`.
    - Fixed path of files in `getFileNames()` to use UNIX path separator ("/").
- **iso**:
    - Excluded directories from files list (`getFileNames()`).
- **7zip**:
    - Fixed result of `deleteFiles()` and `archiveFiles()` in-archive paths.
    - Fixed calculation of compressed file size in `getFileData()`.
    - (#10) Set infinite timeout of `7z` system call (useful for big archives).
- **cab**:
    - Fixed `extractFiles()` functionality.

### 0.1.1 - Sep 21, 2018
API changes:
* **Changed algorithm of files list generation in `archiveFiles()` and `addFiles()`**:
    ```php
    // 1. one file
    $archive->archiveFiles('/var/www/site/abc.log', 'archive.zip'); // => stored as 'abc.log'
    // 2. directory
    $archive->archiveFiles('/var/www/site/runtime/logs', 'archive.zip'); // => directory content stored in archive root
    // 3. list
    $archive->archiveFiles([
          '/var/www/site/abc.log' => 'abc.log', // stored as 'abc.log'
          '/var/www/site/abc.log', // stored as '/var/www/site/abc.log'
          '/var/www/site/runtime/logs' => 'logs', // directory content stored in 'logs' dir
          '/var/www/site/runtime/logs', // stored as '/var/www/site/runtime/logs'
    ], 'archive.zip');
    ```
* **Disabled paths expanding in `extractFiles()` and `deleteFiles()` by default**.

    If you need to expand `src/` path to all files within this directory in archive, set second argument `$expandFilesList` argument to `true`.
    ```php
    $archive->extractFiles(__DIR__, 'src/', true);
    $archive->deleteFiles('tests/', true);
    ```

* Added new element in `archiveFiles()` result in emulation mode. Now it returns an archive with 4 elements: new `type` element with archive type.

Fixes:
* Fixed **LZW-stream** (.tar.Z) wrapper (before it didn't work).
* Fixed **ISO** archives reading (before archive size could be calculated wrong).
* Fixed **CAB** archives extraction in `getFileContent($file)` (before it didn't work).
* Improved extraction in `getFileContent($file)` for **RAR** archives by using streams (before it did extract file in temporarily folder, read it and then delete it).

Improvements:
* Added `isFileExists($file): bool` method for checking if archive has a file with specific name.
* Added `getFileResource($file): resource` method for getting a file descriptor for reading all file content without full extraction in memory.
* Added `canOpenArchive($archiveFileName): bool` and `canOpenType($archiveFormat): bool` static methods to check if specific archive or format can be opened.
* Added `detectArchiveType($fileName): string|false` static method to detect (by filename or content) archive type.
* Added `addFile($file, $inArchiveName = null)` / `addDirectory($directory, $inArchivePath = null)` to add one file or one directory, `archiveFile($file, $archiveName)` / `archiveDirectory($directory, $archiveName)` to archive one file or directory.

Miscellaneous:
* Added simple tests.
* Added `phar` distribution.

### 0.1.0 - Apr 11, 2018
API changes:
* Renamed methods `extractNode()` → `extractFiles()`, `archiveNodes()` → `archiveFiles()`. Original method are still available with `@deprecated` status.
* `getFileData()` now returns `ArchiveEntry` instance instead of `stdClass`. Original object fields are still available with `@deprecated` status.
* `addFiles()` and `deleteFiles()` now return false when archive is not editable.

Improvements:
* Added checks of archive opening status in constructor: now an `Exception` will be throwed if archive file is not readable.
* Some changes in `archiveNodes()` about handling directory names.
* Fixed archive rescan in `addFiles()` and `deleteFiles()`.

Miscellaneous:
* Removed example scripts (`examples/`).
* Code changes: added comments.

### 0.0.11 - Mar 21, 2018
* Cleaned up some old code.
* Added `ext-phar` adapter for tar archives (if `pear/archive_tar` is not installed).

### 0.0.10 - Aug 7, 2017
* Remove `docopt` from requirements. Now it's a suggestion.

### 0.0.9 - Jul 20, 2017
* Added `cam` (Console Archive Manager) script.

### 0.0.8 - Jan 24, 2017
* Added initial support for `CAB` archives without extracting.
* Added handling of short names of tar archives (.tgz/.tbz2/...).
* Removed external repository declaration.
* Removed `die()` in source code.

### 0.0.7 - Jan 14, 2017
* Fixed usage of `ereg` function for PHP >7.

### 0.0.6 - Jan 9, 2017
* Added functionality for adding files in archive.
* Added functionality for deleting files from archive.
* Fixed discovering `7z` archive number of files and creating new archive.

### 0.0.5 - Jan 8, 2017
* Added support for `7z` (by 7zip-cli) archives.

### 0.0.4 - Jan 7, 2017
* Added support for single-file `bz2` (bzip2) and `xz` (lzma2) archives.

### 0.0.3 - Aug 18, 2015
* Removed `archive_tar` from required packages.

### 0.0.2 - May 27, 2014
* Released under the MIT license

### 0.0.1 - May 26, 2014
First version.
