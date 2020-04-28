# Usage

- [Reading and extraction](#reading-and-extraction)
- [Archive modification](#archive-modification)
- [Archiving](#archiving)

## Reading and extraction
1. Import `UnifiedArchive`

    ```php
    require 'vendor/autoload.php';
    use \wapmorgan\UnifiedArchive\UnifiedArchive;
    ```

2. At the beginning, try to open the file with automatic detection of a format
by name. In case of successful recognition an `UnifiedArchive` object will be
returned. In case of failure - _null_ will be returned.

    ```php
    $archive = UnifiedArchive::open('filename.rar');
    // or
    $archive = UnifiedArchive::open('filename.zip');
    // or
    $archive = UnifiedArchive::open('filename.7z');
    // or
    $archive = UnifiedArchive::open('filename.gz');
    // or
    $archive = UnifiedArchive::open('filename.bz2');
    // or
    $archive = UnifiedArchive::open('filename.xz');
    // or
    $archive = UnifiedArchive::open('filename.cab');
    // or
    $archive = UnifiedArchive::open('filename.tar');
    // or
    $archive = UnifiedArchive::open('filename.tar.gz');
    // or
    $archive = UnifiedArchive::open('filename.tar.bz2');
    // or
    $archive = UnifiedArchive::open('filename.tar.xz');
    // or
    $archive = UnifiedArchive::open('filename.tar.Z');
    // or
    $archive = UnifiedArchive::open('filename.iso');
    ```

3. Further, read the list of files of archive.

    ```php
    $files_list = $archive->getFileNames(); // array with files list
   // ['file', 'file2', 'file3', ...]
    ```

4. Further, check that specific file is in archive.

    ```php
    if ($archive->isFileExists('README.md')) {
       // some operations
    }
    ```

5. To get common information about specific file use `getFileData()` method.
This method returns [an `ArchiveEntry` instance](API.md#ArchiveEntry)

    ```php
    $file_data = $archive->getFileData('README.md')); // ArchiveEntry with file information
    ```

6. To get raw file contents use `getFileContent()` method

    ```php
    $file_content = $archive->getFileContent('README.md')); // string
    // raw file content
    ```

7. Further, you can unpack all archive or specific files on a disk. The `extractFiles()` method is intended to it.

    ```php
    $archive->extractFiles(string $outputFolder, string|array $archiveFiles);
    ```

    _Example:_
    ```php
    // to unpack all contents of archive to "output" folder
    $archive->extractFiles(__DIR__.'/output');

    // to unpack specific files (README.md and composer.json) from archive to "output" folder
    $archive->extractFiles(__DIR__.'/output', ['README.md', 'composer.json']);

    // to unpack the "src" catalog with all content from archive into the "sources" catalog on a disk
    $archive->extractFiles(__DIR__.'/output', '/src/', true);
    ```

## Archive modification
Only few archive formats support modification:
- zip
- 7z
- tar (depends on low-level driver for tar - see Formats section for details)

For details go to [Formats support](../README.md#Formats-support) section.

1. Deletion files from archive

    ```php
    // Delete a single file
    $archive->deleteFiles('README.md');

    // Delete multiple files
    $archive->deleteFiles(['README.md', 'MANIFEST.MF']);

    // Delete directory with full content
    $archive->deleteFiles('/src/', true);
    ```

    In case of success the number of successfully deleted files will be returned.

    [Details](API.md#UnifiedArchive--deleteFiles).

2. Addition files to archive

    ```php
    // Add a catalog with all contents with full paths
    $archive->addFiles('/var/log');

    // To add one file (will be stored as one file "syslog")
    $archive->addFiles('/var/log/syslog');

    // To add some files or catalogs (all catalogs structure in paths will be kept)
    $archive->addFiles([$directory, $file, $file2, ...]);
    ```

   [Details](API.md#UnifiedArchive--addFiles).

## Archiving
Only few archive formats support modification:
- zip
- 7z
- tar (with restrictions)

For details go to [Formats support](#Formats-support) section.

To pack completely the catalog with all attached files and subdirectories in new archive:

```php
UnifiedArchive::archiveFiles('/var/log', 'Archive.zip');

// To pack one file
UnifiedArchive::archiveFiles('/var/log/syslog', 'Archive.zip');

// To pack some files or catalogs
UnifiedArchive::archiveFiles([$directory, $file, $file2, ...], 'Archive.zip');
```

Also, there is extended syntax for `addFiles()` and `archiveFiles()`:

```php
UnifiedArchive::archiveFiles([
          '/var/www/site/abc.log' => 'abc.log',   // stored as 'abc.log'
          '/var/www/site/abc.log',                // stored as '/var/www/site/abc.log'
          '/var/www/site/runtime/logs' => 'logs', // directory content stored in 'logs' dir
          '/var/www/site/runtime/logs',           // stored as '/var/www/site/runtime/logs'
    ], 'archive.zip');
```

[Details](API.md#UnifiedArchive--archiveFiles).