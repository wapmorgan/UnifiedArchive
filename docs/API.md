This file describes all UnifiedArchive API.

UnifiedArchive is represented by few basic classes under `\wapmorgan\UnifiedArchive` namespace:
- `UnifiedArchive` - represents an archive and provides related functions.
    - Archive creation:
    - [`UnifiedArchive::canCreateType`](#UnifiedArchive--canCreateType)
    - [`UnifiedArchive::archiveDirectory`](#UnifiedArchive--archiveDirectory)
    - [`UnifiedArchive::archiveFile`](#UnifiedArchive--archiveFile)
    - [`UnifiedArchive::archiveFiles`](#UnifiedArchive--archiveFiles)
    - Archive opening
    - [`UnifiedArchive::canOpenArchive`](#UnifiedArchive--canOpenArchive)
    - [`UnifiedArchive::canOpenType`](#UnifiedArchive--canOpenType)
    - [`UnifiedArchive::open`](#UnifiedArchive--open)
    - Archive information:
        - [`UnifiedArchive::getArchiveType`](#UnifiedArchive--getArchiveType)
        - [`UnifiedArchive::getArchiveSize`](#UnifiedArchive--getArchiveSize)
        - [`UnifiedArchive::countCompressedFilesSize`](#UnifiedArchive--countCompressedFilesSize)
        - [`UnifiedArchive::countUncompressedFilesSize`](#UnifiedArchive--countUncompressedFilesSize)
        - [`UnifiedArchive::countFiles`](#UnifiedArchive--countFiles)
    - Archive content:
        - [`UnifiedArchive::getFileNames`](#UnifiedArchive--getFileNames)
        - [`UnifiedArchive::isFileExists`](#UnifiedArchive--isFileExists)
        - [`UnifiedArchive::getFileData`](#UnifiedArchive--getFileData)
        - [`UnifiedArchive::getFileResource`](#UnifiedArchive--getFileResource)
        - [`UnifiedArchive::getFileContent`](#UnifiedArchive--getFileContent)
        - [`UnifiedArchive::extractFiles`](#UnifiedArchive--extractFiles)
    - Archive modification:
        - [`UnifiedArchive::canAddFiles`](#UnifiedArchive--canAddFiles)
        - [`UnifiedArchive::canDeleteFiles`](#UnifiedArchive--canDeleteFiles)
        - [`UnifiedArchive::addDirectory`](#UnifiedArchive--addDirectory)
        - [`UnifiedArchive::addFile`](#UnifiedArchive--addFile)
        - [`UnifiedArchive::addFiles`](#UnifiedArchive--addFiles)
        - [`UnifiedArchive::deleteFiles`](#UnifiedArchive--deleteFiles)
- [`ArchiveEntry`](#ArchiveEntry) - represents information about specific file from archive. This object can be obtained
by call to one of  `UnifiedArchive` methods.

## UnifiedArchive

### Archive creation

- <span id="UnifiedArchive--canCreateType"></span>
    ```php
    UnifiedArchive::canCreateType(string $type): boolean
    ```

    Tests if an archive format can be created with current system and php configuration.
    _If you want to enabled specific format support, you need to install additional program or php extension. List of
     extensions that should be install can be obtained by execuing built-in `cam` with `--formats` flag: `
     ./vendor/bin/cam --formats`_
    Returns `true` if given archive can be created and `false` otherwise.

- <span id="UnifiedArchive::archiveDirectory"></span>
    ```php
    UnifiedArchive::archiveDirectory(string $directory, string $archiveName): boolean
    ```

    Creates an archive with all content from given directory and saves archive to `$archiveName` (format is
    resolved by extension). All files have relative path in archive.
    If case of success, `true` is returned.

    Throws:
    - `UnsupportedOperationException`
    - `FileAlreadyExistsException`
    - `EmptyFileListException`
    - `ArchiveCreationException`

- <span id="UnifiedArchive--archiveFile"></span><span id="UnifiedArchive--archiveFile"></span>
    ```php
    UnifiedArchive::archiveFile(string $file, string $archiveName): boolean
    ```

    Creates an archive with file `$file` and saves archive to `$archiveName` (format is
    resolved by extension). File has only relative nam in archive.
    If case of success, `true` is returned.

    Throws:
    - `UnsupportedOperationException`
    - `FileAlreadyExistsException`
    - `EmptyFileListException`
    - `ArchiveCreationException`

- <span id="UnifiedArchive--archiveFiles"></span>
    ```php
    UnifiedArchive::archiveFiles(array $files, string $archiveName): int
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

- <span id="UnifiedArchive--canOpenArchive"></span>
    ```php
    UnifiedArchive::canOpenArchive(string $fileName): boolean
    ```

    Tests if an archive (format is resolved by extension) can be opened with current system and php configuration.
    _If you want to enabled specific format support, you need to install additional program or php extension. List of
     extensions that should be install can be obtained by execuing built-in `cam` with `--formats` flag: `
     ./vendor/bin/cam --formats`_
    Returns `true` if given archive can be opened and `false` otherwise.

- <span id="UnifiedArchive--canOpenType"></span>
    ```php
    UnifiedArchive::canOpenType(string $type): boolean
    ```

    Tests if an archive format can be opened with current system and php
    configuration. `$type` should be one of `UnifiedArchive` constants (such as `UnifiedArchive::ZIP` and so on).
    Full list of constants provided in the [appendix of this document](#unifiedArchive-formats-constants).
    Returns `true` if given archive can be opened and `false` otherwise.

- <span id="UnifiedArchive--open"></span>
    ```php
    UnifiedArchive::open(string $fileName): UnifiedArchive|null
    ```

    Opens an archive and returns instance of `UnifiedArchive`.
    In case of failure (format is not supported), `null` is returned.

#### Archive information

All following methods is intended to be called to `UnifiedArchive` instance.

- <span id="UnifiedArchive--getArchiveType"></span>
    ```php
    UnifiedArchive::getArchiveType(): string
    ```

    Returns type of archive as one of `UnifiedArchive` [constants](#unifiedArchive-formats-constants).

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
    UnifiedArchive::getFileData(string $fileName): ArchiveEntry|false
    ```

    Returns `ArchiveEntry` that contains all specific information about file stored in archive and
     described [later in the document](#archiveentry).
    If file is not in archive, `NonExistentArchiveFileException` is thrown.

- <span id="UnifiedArchive--getFileResource"></span>
    ```php
    UnifiedArchive::getFileResource(string $fileName): resource|false
    ```

    Returns a resource of in-archive file that can be used to get it's content (by `fread()` and so on).
    This method of extraction is useful for large files or when you need to read files in portions.
    If file is not in archive, `NonExistentArchiveFileException` is thrown.

- <span id="UnifiedArchive--getFileContent"></span>
    ```php
    UnifiedArchive::getFileContent(string $fileName): resource|false
    ```

    Returns content of in-archive file as raw string.
    If file is not in archive, `NonExistentArchiveFileException` is thrown.

- <span id="UnifiedArchive--extractFiles"></span>
    ```php
    UnifiedArchive::extractFiles(string $outputFolder): int|false
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

- <span id="UnifiedArchive--canAddFiles"></span>
    ```php
    UnifiedArchive::canAddFiles(): boolean
    ```

    Returns `true` if there's ability of adding files to this archive.

- <span id="UnifiedArchive--canDeleteFiles"></span>
    ```php
    UnifiedArchive::canDeleteFiles(): boolean
    ```

    Returns `true` if there's ability of deleting files from this archive.

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

    Packs file in archive. If in-archive path is not specified, file will have it's original path.
    If in-archive path is set, file wil be packed with given path.
    If case of success, `true` is returned.

    Throws:
    - `EmptyFileListException`
    - `UnsupportedOperationException`
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

## UnifiedArchive formats constants
- `UnifiedArchive::ZIP`
- `UnifiedArchive::SEVEN_ZIP`
- `UnifiedArchive::RAR`
- `UnifiedArchive::GZIP`
- `UnifiedArchive::BZIP`
- `UnifiedArchive::LZMA`
- `UnifiedArchive::ISO`
- `UnifiedArchive::CAB`
- `UnifiedArchive::TAR`
- `UnifiedArchive::TAR_GZIP`
- `UnifiedArchive::TAR_BZIP`
- `UnifiedArchive::TAR_LZMA`
- `UnifiedArchive::TAR_LZW`

