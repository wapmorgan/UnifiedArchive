# Change Log

### 0.2.0 - ***
* Added `isFileExists($file)` method for checking if archive has a file with specific name.
* Added `getFileResource($file)` method for getting a file descriptor for reading all file content without full extraction in memory.
* Added `canOpenArchive($archiveFileName)` and `canOpenType($archiveFormat)` static methods to check if specific archive or format can be opened.
* Added `detectArchiveType($fileName)` static method to detect (by filename or content) archive type. 
* Added simple tests.
* Added `phar` distribution.
* Changed algorithm of files list generation in `archiveFiles()` and `addFiles()`:
  - If `$files` is a string containing one file name, then this file will be stored with it's basename in archive root.
  - If `$files` is a string containing one directory name, then all files from this directory will be stored in archive root with relative paths.\
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
* Changed paths expanding in `extractFiles()` and `deleteFiles()`. Now no paths expanding is performing by default. If you need to expand `src/` path to all files within this directory in archive, set `$expandFilesList` argument to `true`.
    ```php
    $archive->extratFiles(__DIR__, 'src/', true);
    $archive->deleteFiles('tests/', true);
    ```
* Changed result format of `archiveFiles()` in emulation mode. Now it also returns type of archive in `type` archive element. 
* Fixed LZW-stream wrapper.        

### 0.1.0 - Apr 11, 2018
* Renamed methods `extractNode()` -> `extractFiles()`, `archiveNodes()` -> `archiveFiles()`. 
* Added checks for archive format. 
* Changed `getFileData()` output from `stdclass` object to `ArchiveEntry`.

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