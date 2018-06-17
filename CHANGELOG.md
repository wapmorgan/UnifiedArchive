# Change Log

### 0.2.0 - ***
Fixes:
* Fixed **LZW-stream** (.tar.Z) wrapper (before it didn't work).
* Fixed **ISO** archives reading (before archive size could be calculated wrong).
* Fixed **CAB** archives extraction in `getFileContent($file)` (before it didn't work).
* Improved extraction in `getFileContent($file)` for **RAR** archives by using streams (before it did extract file in temporarily folder, read it and then delete it).

API changes:
* **Changed algorithm of files list generation in `archiveFiles($files, $archiveFileName, $emulation = false)` and `addFiles($files)`**:
  - If `$files` is a string containing one file name, then this file will be stored with it's basename in archive root.
  - If `$files` is a string containing one directory name, then all files from this directory will be stored in archive root with relative paths.
  - If `$files` is an array containing file and directory names, then two options:
      
    - `$source => $destination` format, where `$source` is a file/directory on your drive, and `$destination` is a target path in archive.
        ```php
        $files = [
            '/home/test/files' => 'data',
            '/home/test/pictures' => 'data'
        ]; // all files will be saved in "data/" folder in archive.
        ```
    - `$source` format. In this case all files/directories will be saved with full paths in archive.
        ```php
        $files = [
            '/home/test/files', // will be saved with path "/home/test/files" in archive
            '/home/test/pictures',
        ];
        ```
* **Disabled paths expanding in `extractFiles()` and `deleteFiles()` by default**.

    If you need to expand `src/` path to all files within this directory in archive, set `$expandFilesList` argument to `true`.
    ```php
    $archive->extratFiles(__DIR__, 'src/', true);
    $archive->deleteFiles('tests/', true);
    ```
* Changed result format of `archiveFiles()` in emulation mode. Now it returns an archive with 4 elements (added `type` element with archive type).

Improvements:
* Added `isFileExists($file): bool` method for checking if archive has a file with specific name.
* Added `getFileResource($file): resource` method for getting a file descriptor for reading all file content without full extraction in memory.
* Added `canOpenArchive($archiveFileName): bool` and `canOpenType($archiveFormat): bool` static methods to check if specific archive or format can be opened.
* Added `detectArchiveType($fileName): string|false` static method to detect (by filename or content) archive type.

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