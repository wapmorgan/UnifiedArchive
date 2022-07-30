# Usage

- [Reading and extraction](#reading-and-extraction)
- [Archive modification](#archive-modification)
- [Archiving](#archiving)

## Reading and extraction
1. Open the file with automatic detection of a format by name or content. In case of successful, an `UnifiedArchive`
   object will be returned. In case of failure - _null_ will be returned.
    ```php
    require 'vendor/autoload.php';
    use \wapmorgan\UnifiedArchive\UnifiedArchive;

    $archive = UnifiedArchive::open('filename.rar');
    // or
    $archive = UnifiedArchive::open('filename.zip', null, 'password');
    // or
    $archive = UnifiedArchive::open('filename.tar', [
        \wapmorgan\UnifiedArchive\Drivers\BasicDriver::EXTRACT_CONTENT,
        \wapmorgan\UnifiedArchive\Drivers\BasicDriver::STREAM_CONTENT,
        \wapmorgan\UnifiedArchive\Drivers\BasicDriver::APPEND,
    ]);
    // or
    $archive = UnifiedArchive::open('filename.7z', null, 'password');
    ```

2. Read the list of files of archive or check that file is in archive.

   ```php
   foreach($archive->getFileNames() as $filename) { // ['file', 'file2', 'file3', ...]
        // ...
   }
   
   foreach($archive->getFileNames('*.txt') as $filename) { // ['README.txt', 'doc.txt', ...]
        // ...
   }
   
   foreach ($archive as $filename) {
        // ...
   }
   
   if ($archive->hasFile('README.md')) {
       // some operations
   }
   ```

3. To get common information about concrete file use `getFileData()` method.
This method returns [an `ArchiveEntry` instance](API.md#ArchiveEntry). 
To get raw file contents use `getFileContent()` method, to get stream for file use `getFileStream()` method.

   ```php
   // file meta
   $file_data = $archive->getFileData('README.md')); // ArchiveEntry with file information
   echo 'Original size is ' . $file_data->uncompressedSize.PHP_EOL;
   echo 'Modification datetime is ' . date('r', $file_data->modificationTime).PHP_EOL;

   // raw file content
   $file_content = $archive->getFileContent('README.md')); // string

   // pass stream to standard output
   fpassthru($archive->getFileStream('README.md'));
   ```

4. Unpack all archive or specific files on a disk - `extractFiles()`.

    ```php
    // to unpack all contents of archive to "output" folder
    $archive->extractFiles(__DIR__.'/output');

    // to unpack specific files (README.md and composer.json) from archive to "output" folder
    $archive->extractFiles(__DIR__.'/output', ['README.md', 'composer.json']);

    // to unpack the "src" catalog with all content from archive into the "sources" catalog on a disk
    $archive->extractFiles(__DIR__.'/output', 'src/', true);
    ```

## Archive modification
Only few archive formats support modification: (zip, 7z, tar) - it depends on low-level driver - see **Formats** page for details.

1. [Delete files](API.md#UnifiedArchive--deleteFiles) from archive

    ```php
    // Delete a single file
    $archive->deleteFiles('README.md');

    // Delete multiple files
    $archive->deleteFiles(['README.md', 'MANIFEST.MF']);

    // Delete directory with full content
    $archive->deleteFiles('src/', true);
    ```

    In case of success the number of successfully deleted files will be returned.

2. [Add files](API.md#UnifiedArchive--addFiles) to archive

    ```php
    // Add a catalog with all contents with full paths
    $archive->addFiles('/var/log/');

    // To add one file (will be stored as one file "syslog")
    $archive->addFiles('/var/log/syslog');

    // To add some files or catalogs (all catalogs structure in paths will be kept)
    $archive->addFiles([$directory, $file, $file2, ...]);
    ```

## Archiving
Only few archive formats support modification: zip, 7z, tar (with restrictions).

To pack completely the catalog with all attached files and subdirectories in new archive:

```php
# archive all folder content
UnifiedArchive::archiveFiles('/var/log', 'Archive.zip');

# archive one file
UnifiedArchive::archiveFiles('/var/log/syslog', 'Archive.zip');

# archive few files / folders
UnifiedArchive::archiveFiles([$directory, $file, $file2, ...], 'Archive.zip');
```

Also, there is [extended syntax](API.md#UnifiedArchive--archiveFiles) for `addFiles()` and `archiveFiles()`:

```php
UnifiedArchive::archiveFiles([
          'abc.log' => '/var/www/site/abc.log',   // stored as 'abc.log'
          '/var/www/site/abc.log',                // stored as '/var/www/site/abc.log'
          'logs' => '/var/www/site/runtime/logs', // directory content stored in 'logs' dir
          '/var/www/site/runtime/logs',           // stored as '/var/www/site/runtime/logs'
          '' => ['/home/user1/docs', '/home/user2/docs'], // user1 and user2 docs stored in archive root
    ], 'archive.zip');
```
