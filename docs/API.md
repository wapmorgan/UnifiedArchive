This file describes all UnifiedArchive API.

UnifiedArchive is represented by few basic classes under `\wapmorgan\UnifiedArchive` namespace:

1. [`Formats`](#Formats) keeps information about formats support and specific format functions.
2. [`UnifiedArchive`](#UnifiedArchive) - represents an archive and provides related functions.
3. [`ArchiveEntry`](#ArchiveEntry) - represents information about a specific file from archive. This object can be obtained
   by call to one of  `UnifiedArchive` methods.

# Formats

`$format` should be one of `Formats` constants (such as `Formats::ZIP` and so on).
Full list of constants provided in the [appendix of this document](#formats-list).
_If you want to enabled specific format support, you need to install an additional program or php extension. List of
extensions that should be installed can be obtained by executing built-in `cam`:
`./vendor/bin/cam system:drivers`_

All methods are static.

| Method                         | Arguments                                            | Result           | Description                                                                                                                                                                                  |
|--------------------------------|------------------------------------------------------|------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Formats::detectArchiveFormat` | `string $archiveFileName, bool $contentCheck = true` | `string/false` | Detects a format of given archive `$archiveFileName`. Checks file name and file content (if `$contentCheck = true`). Returns one of `Formats` constant or `false` if format is not detected. |
| `Formats::canOpen`             | `string $format`                                     | `boolean`        | Tests if an archive format can be opened by any driver with current system and php configuration.                                                                                            |
| `Formats::canStream`           | `string $format`                                     | `boolean`        | Tests if a specified archive can be streamed (getFileStream).                                                                                                                                |
| `Formats::canCreate`           | `string $format`                                     | `boolean`        | Tests if an archive format can be created by any driver with current system and php configuration.                                                                                           |
| `Formats::canAppend`           | `string $format`                                     | `boolean`        | Tests if an archive format can be appended (add).                                                                                                                                       |
| `Formats::canUpdate`           | `string $format`                                     | `boolean`        | Tests if an archive format can be updated (delete).                                                                                                                                     |
| `Formats::canEncrypt`          | `string $format`                                     | `boolean`        | Tests if an archive format can be encrypted or opened with encryption by any driver with new files.                                                                                          |
| `Formats::checkFormatSupportAbility` | string `$format, int $ability` | boolean | Check if any driver supports passed ability for passed format |
| `Formats::getFormatMimeType`   | `string $format`                                     | `string/false` | Returns mime type for passed format. Returns `false` if not found.                                                                                                                           |

# UnifiedArchive

- Opening an archive
  - [`UnifiedArchive::canOpen`](#UnifiedArchive--canOpen)
  - [`UnifiedArchive::open`](#UnifiedArchive--open)
  - [`UnifiedArchive->getPclZipInterface`](#UnifiedArchive--getPclZipInterface)
- Archive information:
  - `UnifiedArchive->getFormat`
  - `UnifiedArchive->getSize`
  - `UnifiedArchive->getCompressedSize`
  - `UnifiedArchive->getOriginalSize`
  - `UnifiedArchive->countFiles`
  - `UnifiedArchive->getComment`
- Extracting an archive:
  - [`UnifiedArchive->getFiles`](#UnifiedArchive--getFiles)
  - [`UnifiedArchive->hasFile`](#UnifiedArchive--hasFile)
  - [`UnifiedArchive->getFileData`](#UnifiedArchive--getFileData)
  - [`UnifiedArchive->getFileStream`](#UnifiedArchive--getFileStream)
  - [`UnifiedArchive->getFileContent`](#UnifiedArchive--getFileContent)
  - [`UnifiedArchive->extract`](#UnifiedArchive--extract)
- Updating an archive:
  - [`UnifiedArchive->addDirectory`](#UnifiedArchive--addDirectory)
  - [`UnifiedArchive->addFile`](#UnifiedArchive--addFile)
  - [`UnifiedArchive->addFileFromString`](#UnifiedArchive--addFileFromString)
  - [`UnifiedArchive->add`](#UnifiedArchive--add)
  - [`UnifiedArchive->delete`](#UnifiedArchive--delete)
- Making an archive:
  - [`UnifiedArchive::archiveDirectory`](#UnifiedArchive--archiveDirectory)
  - [`UnifiedArchive::archiveFile`](#UnifiedArchive--archiveFile)
  - [`UnifiedArchive::archive`](#UnifiedArchive--archive)

## Archive opening

- <span id="UnifiedArchive--canOpen"></span>
    ```php
    UnifiedArchive::canOpen(string $fileName): boolean
    ```

    Tests if an archive (format is resolved by extension) can be opened with current system and php configuration.
    _If you want to enabled specific format support, you need to install an additional program or php extension. List of
     extensions that should be installed can be obtained by executing built-in `cam`: `
     ./vendor/bin/cam system:formats`_
    Returns `true` if given archive can be opened and `false` otherwise.

- <span id="UnifiedArchive--open"></span>
    ```php
    UnifiedArchive::open(
        string $fileName,
        int[] $abilities = [],
        ?string $password = null
    ): UnifiedArchive|null
    ```

    Opens an archive and returns instance of `UnifiedArchive`.
    If you provide `$password`, it will be used to open encrypted archive.
    If you provide `$abilities`, it will be used to determine driver for format, that supports ALL of passed abilities.
    In case of failure (format is not supported or recognized), null will be returned.

- <span id="UnifiedArchive--getPclZipInterface"></span>
    ```php
    UnifiedArchive::getPclZipInterface(): PclzipZipInterface
    ```

    Returns a `PclzipZipInterface` handler for an archive. It provides all PclZip functions in PclZip-like interface for an archive.

## Archive information

All following methods is intended to be called to `UnifiedArchive` instance.

| Method                                | Result         | Description                                                                                                                                  |
|---------------------------------------|----------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| `UnifiedArchive::getFormat()`         | `string`       | Returns format of archive as one of `Formats` constants.                                                                                     |
| `UnifiedArchive::getMimeType()`       | `string/false` | Returns mime type of archive.                                                                                                                |
| `UnifiedArchive::getSize()`           | `int`          | Returns size of archive file in bytes.                                                                                                       |
| `UnifiedArchive::getCompressedSize()` | `int`          | Returns size of all stored files in archive with archive compression in bytes. This can be used to measure efficiency of format compression. |
| `UnifiedArchive::getOriginalSize()`   | `int`          | Returns size of all stored files in archive without compression in bytes. This can be used to measure size that extracted files will use.    |
| `UnifiedArchive::countFiles()`        | `int`          | Returns number of files stored in an archive.                                                                                                |
| `UnifiedArchive::getComment()`        | `?string`      | Returns comment of archive or null (if not supported nor present).                                                                           |

## Archive content

- <span id="UnifiedArchive--getFiles"></span>
    ```php
    UnifiedArchive::getFiles(?string $filter = null): string[]
    ```
    Returns full list of files stored in an archive. If `$filter` is passed, will return only matched by `fnmatch()` files. 

- <span id="UnifiedArchive--hasFile"></span>
    ```php
    UnifiedArchive::hasFile(string $fileName): boolean
    ```
    Checks whether file is presented in an archive.

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

- <span id="UnifiedArchive--extract"></span>
    ```php
    UnifiedArchive::extract(string $outputFolder): int
    ```

    Extracts all archive content with full paths to output folder and rewriting existing files.
    In case of success, number of extracted files is returned.

    Throws:
    - `ArchiveExtractionException`

- <span id="UnifiedArchive--extract"></span>
    ```php
    UnifiedArchive::extract(
        string $outputFolder,
        array $files,
        boolean $expandFilesList = false
    ): int|false
    ```

    Extracts given files or directories to output folder. If directories is passed, you need to use
    `$expandFilesList` feature that will expand directory names to all nested files (e.g `dir` will be expanded to
    `dir/file1, dir/file2, dir/subdir/file3`).
    In case of success, number of extracted files is returned.

    Throws:
    - `EmptyFileListException`
    - `ArchiveExtractionException`

## Archive modification

- <span id="UnifiedArchive--addDirectory"></span>
    ```php
    UnifiedArchive::addDirectory(
        string $directory,
        string $inArchivePath = null
    ): boolean
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
    UnifiedArchive::addFile(
        string $file,
        string $inArchiveName = null
    ): boolean
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
    UnifiedArchive::addFileFromString(
        string $inArchiveName,
        string $content
    ): boolean
    ```

    Packs file in an archive. If case of success, `true` is returned.

    Throws:
    - `ArchiveModificationException`

- <span id="UnifiedArchive--add"></span>
    ```php
    UnifiedArchive::add(array $files): int|false
    ```

    Packs given `$files` into archive. `$files` is an array of files or directories.
    If file/directory passed with numeric key (e.g `['file', 'directory']`), then file/directory will have it's full
    path in archive. If file/directory is a key (e.g `['in_archive_path' => 'file1']`), then file/directory will have
    path as it's key.
    If any error occurred (such as file already exists, files list is empty, ...), an `\Exception` is throwing.
    In case of success, number of packed files will be returned.

    Throws:
    - `EmptyFileListException`
    - `UnsupportedOperationException`
    - `ArchiveModificationException`

- <span id="UnifiedArchive--delete"></span>
    ```php
    UnifiedArchive::delete(
        string|array $fileOrFiles,
        $expandFilesList = false
    ): int|false
    ```

    Deletes passed `$fileOrFiles` from archive. `$fileOrFiles` is a string with file/directory name or an array
    of files or directories.
    In case of success, number of deleted files will be returned.

    Throws:
    - `EmptyFileListException`
    - `UnsupportedOperationException`
    - `ArchiveModificationException`

## Making an archive

- <span id="UnifiedArchive::archiveDirectory"></span>
    ```php
    UnifiedArchive::archiveDirectory(
        string $directory,
        string $archiveName,
        int $compressionLevel = BasicFormat::COMPRESSION_AVERAGE,
        ?string $password = null
    ): boolean
    ```

  Creates an archive with all content from given directory and saves archive to `$archiveName` (format is
  resolved by extension). All files have relative path in the archive. By `$compressionLevel` you can adjust
  compression level for files. By `$password` you can set password for an archive. If case of success, `true` is returned.

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
    UnifiedArchive::archiveFile(
        string $file,
        string $archiveName,
        int $compressionLevel = BasicFormat::COMPRESSION_AVERAGE,
        ?string $password = null
    ): boolean
    ```

  Creates an archive with file `$file` and saves archive to `$archiveName` (format is
  resolved by extension). File will have only relative name in the archive.
  If case of success, `true` is returned.

  Throws:
  - `UnsupportedOperationException`
  - `FileAlreadyExistsException`
  - `EmptyFileListException`
  - `ArchiveCreationException`

- <span id="UnifiedArchive--archive"></span>
    ```php
    UnifiedArchive::archive(
        array $files,
        string $archiveName,
        int $compressionLevel = BasicFormat::COMPRESSION_AVERAGE,
        ?string $password = null
    ): int
    ```

  Creates an archive with given `$files` list. `$files` is an array of files or directories.
  If file/directory passed with numeric key (e.g `['file', 'directory']`), then file/directory will have it's full
  path in an archive. If file/directory is a key (e.g `['in_archive_path' => 'file1']`), then file/directory will have
  path as it's value.
  In case of success, number of stored files will be returned.

  Throws:
  - `UnsupportedOperationException`
  - `FileAlreadyExistsException`
  - `EmptyFileListException`
  - `ArchiveCreationException`

# ArchiveEntry

The class represents a file from archive as result of a call to `UnifiedArchive::getFileData(string $fileName)`.
It contains fields with file information:

- `string $path` - full file path in archive.
- `integer $modificationTime` - time of change of the file (the integer value containing number of seconds passed
- `boolean $isCompressed` - a flag indicates if file stored with compression.
- `integer $compressedSize` - size of the file with compression in bytes.
- `integer $uncompressedSize` - size of the file without compression in bytes.
since the beginning of an era of Unix).

### Formats list
- `Formats::ZIP`
- `Formats::SEVEN_ZIP`
- `Formats::RAR`
- `Formats::CAB`
- `Formats::TAR`
- `Formats::TAR_GZIP`
- `Formats::TAR_BZIP`
- `Formats::TAR_LZMA`
- `Formats::TAR_LZW`
- `Formats::ARJ`
- `Formats::GZIP`
- `Formats::BZIP`
- `Formats::LZMA`
- `Formats::UEFI`
- `Formats::GPT`
- `Formats::MBR`
- `Formats::MSI`
- `Formats::ISO`
- `Formats::DMG`
- `Formats::UDF`
- `Formats::RPM`
- `Formats::DEB`
