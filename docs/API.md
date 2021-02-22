This file describes all UnifiedArchive API.

UnifiedArchive is represented by few basic classes under `\wapmorgan\UnifiedArchive` namespace:

1. `Formats` keeps information about formats support and specific format functions.
- [`Formats::detectArchiveFormat`](#Formats--detectArchiveFormat)
- [`Formats::canOpen`](#Formats--canOpen)
- [`Formats::canCreate`](#Formats--canCreate)
- [`Formats::canAppend`](#Formats--canAppend)
- [`Formats::canUpdate`](#Formats--canUpdate)
- [`Formats::canEncrypt`](#Formats--canEncrypt)

2. `UnifiedArchive` - represents an archive and provides related functions.
  - Making an archive:
    - [`UnifiedArchive::archiveDirectory`](#UnifiedArchive--archiveDirectory)
    - [`UnifiedArchive::archiveFile`](#UnifiedArchive--archiveFile)
    - [`UnifiedArchive::archiveFiles`](#UnifiedArchive--archiveFiles)
  - Opening an archive
    - [`UnifiedArchive::canOpen`](#UnifiedArchive--canOpen)
    - [`UnifiedArchive::open`](#UnifiedArchive--open)
  - Archive information:
    - [`UnifiedArchive->getArchiveType`](#UnifiedArchive--getArchiveType)
    - [`UnifiedArchive->getArchiveSize`](#UnifiedArchive--getArchiveSize)
    - [`UnifiedArchive->countCompressedFilesSize`](#UnifiedArchive--countCompressedFilesSize)
    - [`UnifiedArchive->countUncompressedFilesSize`](#UnifiedArchive--countUncompressedFilesSize)
    - [`UnifiedArchive->countFiles`](#UnifiedArchive--countFiles)
  - Extracting an archive:
    - [`UnifiedArchive->getFileNames`](#UnifiedArchive--getFileNames)
    - [`UnifiedArchive->isFileExists`](#UnifiedArchive--isFileExists)
    - [`UnifiedArchive->getFileData`](#UnifiedArchive--getFileData)
    - [`UnifiedArchive->getFileStream`](#UnifiedArchive--getFileStream)
    - [`UnifiedArchive->getFileContent`](#UnifiedArchive--getFileContent)
    - [`UnifiedArchive->extractFiles`](#UnifiedArchive--extractFiles)
  - Updating an archive:
    - [`UnifiedArchive->addDirectory`](#UnifiedArchive--addDirectory)
    - [`UnifiedArchive->addFile`](#UnifiedArchive--addFile)
    - [`UnifiedArchive->addFileFromString`](#UnifiedArchive--addFileFromString)
    - [`UnifiedArchive->addFiles`](#UnifiedArchive--addFiles)
    - [`UnifiedArchive->deleteFiles`](#UnifiedArchive--deleteFiles)

3. [`ArchiveEntry`](#ArchiveEntry) - represents information about a specific file from archive. This object can be obtained
by call to one of  `UnifiedArchive` methods.

## Formats

- <span id="Formats--detectArchiveFormat"></span>
    ```php
    Formats::detectArchiveFormat(string $archiveFileName, bool $contentCheck = true): string|false
    ```
  
    Detects a format of given archive `$archiveFileName`. Checks file name and file content (if `$contentCheck = true`).
    Returns one of `Formats` constant or `false` if format is not detected.

- <span id="Formats--canOpen"></span>
    ```php
    Formats::canOpen(string $format): boolean
    ```

  Tests if an archive format can be opened by any driver with current system and php configuration.
  `$format` should be one of `Formats` constants (such as `Formats::ZIP` and so on).
  Full list of constants provided in the [appendix of this document](#formats-formats-constants).
  _If you want to enabled specific format support, you need to install an additional program or php extension. List of
  extensions that should be installed can be obtained by executing built-in `cam` with `--formats` flag: `
  Returns `true` if given archive can be opened and `false` otherwise.
  ./vendor/bin/cam --formats`_
  Returns `true` if given archive can be opened and `false` otherwise.

- <span id="Formats--canCreate"></span>
    ```php
    Formats::canCreate(string $format): boolean
    ```

  Tests if an archive format can be created by any driver with current system and php configuration.

- <span id="Formats--canAppend"></span>
    ```php
    Formats::canAppend(string $format): boolean
    ```

  Tests if an archive format can be appended by any driver with new files with current system and php configuration.

- <span id="Formats--canUpdate"></span>
    ```php
    Formats::canUpdate(string $format): boolean
    ```

  Tests if an archive format can be updated by any driver with new files with current system and php configuration.

- <span id="Formats--canEncrypt"></span>
    ```php
    Formats::canEncrypt(string $format): boolean
    ```

  Tests if an archive format can be encrypted or opened with encryption by any driver with new files with current system and php configuration.


### Formats formats constants
- `Formats::ZIP`
- `Formats::SEVEN_ZIP`
- `Formats::RAR`
- `Formats::GZIP`
- `Formats::BZIP`
- `Formats::LZMA`
- `Formats::ISO`
- `Formats::CAB`
- `Formats::TAR`
- `Formats::TAR_GZIP`
- `Formats::TAR_BZIP`
- `Formats::TAR_LZMA`
- `Formats::TAR_LZW`

## UnifiedArchive

### Archive creation

- <span id="UnifiedArchive::archiveDirectory"></span>
    ```php
    UnifiedArchive::archiveDirectory(string $directory, string $archiveName, int $compressionLevel = BasicFormat::COMPRESSION_AVERAGE): boolean
    ```

    Creates an archive with all content from given directory and saves archive to `$archiveName` (format is
    resolved by extension). All files have relative path in the archive. By `$compressionLevel` you can adjust
    compression level for files. If case of success, `true` is returned.

    Available values for compression:
    - `BasicFormat::COMPRESSION_NONE`
    - `BasicFormat::COMPRESSION_WEAK`
    - `BasicFormat::COMPRESSION_AVERAGE`
    - `BasicFormat::COMPRESSION_STRONG`
    - `BasicFormat::COMPRESSION_MAXIMUM`

    Throws:
    - `UnsupportedOperationException`
    - `FileAlreadyExistsException`
    - `EmptyFileListException`
    - `ArchiveCreationException`

- <span id="UnifiedArchive--archiveFile"></span><span id="UnifiedArchive--archiveFile"></span>
    ```php
    UnifiedArchive::archiveFile(string $file, string $archiveName, int $compressionLevel = BasicFormat::COMPRESSION_AVERAGE): boolean
    ```

    Creates an archive with file `$file` and saves archive to `$archiveName` (format is
    resolved by extension). File will has only relative name in the archive.
    If case of success, `true` is returned.

    Throws:
    - `UnsupportedOperationException`
    - `FileAlreadyExistsException`
    - `EmptyFileListException`
    - `ArchiveCreationException`

- <span id="UnifiedArchive--archiveFiles"></span>
    ```php
    UnifiedArchive::archiveFiles(array $files, string $archiveName, int $compressionLevel = BasicFormat::COMPRESSION_AVERAGE): int
    ```

    Creates an archive with given `$files` list. `$files` is an array of files or directories.
    If file/directory passed with numeric key (e.g `['file', 'directory']`), then file/directory will have it's full
    path in archive. If file/directory is a key (e.g `['file1' => 'in_archive_path']`), then file/directory will have
    path as it's value.
    In case of success, number of stored files will be returned.

    Throws:
    - `UnsupportedOperationException`
    - `FileAlreadyExistsException`
    - `EmptyFileListException`
    - `ArchiveCreationException`

### Archive opening

- <span id="UnifiedArchive--canOpen"></span>
    ```php
    UnifiedArchive::canOpen(string $fileName): boolean
    ```

    Tests if an archive (format is resolved by extension) can be opened with current system and php configuration.
    _If you want to enabled specific format support, you need to install an additional program or php extension. List of
     extensions that should be installed can be obtained by executing built-in `cam` with `--formats` flag: `
     ./vendor/bin/cam --formats`_
    Returns `true` if given archive can be opened and `false` otherwise.

- <span id="UnifiedArchive--open"></span>
    ```php
    UnifiedArchive::open(string $fileName, ?string $password = null): UnifiedArchive|null
    ```

    Opens an archive and returns instance of `UnifiedArchive`.
    In case of failure (format is not supported), `null` is returned.
    If you provide `$password`, it will be used to open encrypted archive.
    In case you provide password for an archive that don't support it, an `UnsupportedOperationException` will be throwed.

#### Archive information

All following methods is intended to be called to `UnifiedArchive` instance.

- <span id="UnifiedArchive--getArchiveFormat"></span>
    ```php
    UnifiedArchive::getArchiveFormat(): string
    ```

    Returns format of archive as one of `Formats` [constants](#formats-formats-constants).

- <span id="UnifiedArchive--getArchiveSize"></span>
    ```php
    UnifiedArchive::getArchiveSize(): int
    ```
    Returns size of archive file in bytes.
- <span id="UnifiedArchive--countCompressedFilesSize"></span>
    ```php
    UnifiedArchive::countCompressedFilesSize(): int
    ```

    Returns size of all stored files in archive with archive compression in bytes.
    This can be used to measure efficiency of format compression.

- <span id="UnifiedArchive--countUncompressedFilesSize"></span>
    ```php
    UnifiedArchive::countUncompressedFilesSize(): int
    ```

    Returns size of all stored files in archive without compression in bytes.
    This can be used to measure size that extracted files will use.

- <span id="UnifiedArchive--countFiles"></span>
    ```php
    UnifiedArchive::countFiles(): int
    ```
    Returns number of files stored in archive.

#### Archive content

- <span id="UnifiedArchive--getFileNames"></span>
    ```php
    UnifiedArchive::getFileNames(): string[]
    ```
    Returns full list of files stored in archive.

- <span id="UnifiedArchive--isFileExists"></span>
    ```php
    UnifiedArchive::isFileExists(string $fileName): boolean
    ```
    Checks whether file is presented in archive.

- <span id="UnifiedArchive--getFileData"></span>
    ```php
    UnifiedArchive::getFileData(string $fileName): ArchiveEntry
    ```

    Returns `ArchiveEntry` that contains all specific information about file stored in archive and
     described [later in the document](#archiveentry).
    If file is not in archive, `NonExistentArchiveFileException` is thrown.

- <span id="UnifiedArchive--getFileStream"></span>
    ```php
    UnifiedArchive::getFileStream(string $fileName): resource
    ```

    Returns a resource of in-archive file that can be used to get it's content (by `fread()` and so on).
    This method of extraction is useful for large files or when you need to read files in portions.
    If file is not in archive, `NonExistentArchiveFileException` is thrown.

- <span id="UnifiedArchive--getFileContent"></span>
    ```php
    UnifiedArchive::getFileContent(string $fileName): string
    ```

    Returns content of in-archive file as raw string.
    If file is not in archive, `NonExistentArchiveFileException` is thrown.

- <span id="UnifiedArchive--extractFiles"></span>
    ```php
    UnifiedArchive::extractFiles(string $outputFolder): int
    ```

    Extracts all archive content with full paths to output folder and rewriting existing files.
    In case of success, number of extracted files is returned.

    Throws:
    - `ArchiveExtractionException`

- <span id="UnifiedArchive--extractFiles"></span>
    ```php
    UnifiedArchive::extractFiles(string $outputFolder, array $files, boolean $expandFilesList = false): int|false
    ```

    Extracts given files or directories to output folder. If directories is passed, you need to use
    `$expandFilesList` feature that will expand directory names to all nested files (e.g `dir` will be expanded to
    `dir/file1, dir/file2, dir/subdir/file3`).
    In case of success, number of extracted files is returned.

    Throws:
    - `EmptyFileListException`
    - `ArchiveExtractionException`

#### Archive modification

- <span id="UnifiedArchive--addDirectory"></span>
    ```php
    UnifiedArchive::addDirectory(string $directory, string $inArchivePath = null): boolean
    ```

    Packs all nested files from `$directory` in archive. If in-archive path is not specified, all contents will be
    stored with full directory path. If in-archive path is set, all nested files will have given in-archive path.
    If case of success, `true` is returned.

    Throws:
    - `EmptyFileListException`
    - `UnsupportedOperationException`
    - `ArchiveModificationException`

- <span id="UnifiedArchive--addFile"></span>
    ```php
    UnifiedArchive::addFile(string $file, string $inArchiveName = null): boolean
    ```

    Packs file in an archive. If in-archive path is not specified, file will have it's original path.
    If in-archive path is set, file wil be packed with given path.
    If case of success, `true` is returned.

    Throws:
    - `EmptyFileListException`
    - `UnsupportedOperationException`
    - `ArchiveModificationException`

- <span id="UnifiedArchive--addFileFromString"></span>
    ```php
    UnifiedArchive::addFileFromString(string $inArchiveName, string $content): boolean
    ```

    Packs file in an archive. If case of success, `true` is returned.

    Throws:
    - `ArchiveModificationException`

- <span id="UnifiedArchive--addFiles"></span>
    ```php
    UnifiedArchive::addFiles(array $files): int|false
    ```

    Packs given `$files` into archive. `$files` is an array of files or directories.
    If file/directory passed with numeric key (e.g `['file', 'directory']`), then file/directory will have it's full
    path in archive. If file/directory is a key (e.g `['file1' => 'in_archive_path']`), then file/directory will have
    path as it's value.
    If any error occurred (such as file already exists, files list is empty, ...), an `\Exception` is throwing.
    In case of success, number of packed files will be returned.

    Throws:
    - `EmptyFileListException`
    - `UnsupportedOperationException`
    - `ArchiveModificationException`

- <span id="UnifiedArchive--deleteFiles"></span>
    ```php
    UnifiedArchive::deleteFiles(string|array $fileOrFiles, $expandFilesList = false): int|false
    ```

    Deletes passed `$fileOrFiles` from archive. `$fileOrFiles` is a string with file/directory name or an array
    of files or directories.
    In case of success, number of deleted files will be returned.

    Throws:
    - `EmptyFileListException`
    - `UnsupportedOperationException`
    - `ArchiveModificationException`

## ArchiveEntry

The class represents a file from archive as result of a call to `UnifiedArchive::getFileData(string $fileName)`.
It contains fields with file information:

- `string $path` - full file path in archive.
- `integer $modificationTime` - time of change of the file (the integer value containing number of seconds passed
- `boolean $isCompressed` - a flag indicates if file stored with compression.
- `integer $compressedSize` - size of the file with compression in bytes.
- `integer $uncompressedSize` - size of the file without compression in bytes.
since the beginning of an era of Unix).
