# Usage

- [Reading and extraction](#reading-and-extraction)
- [Archive modification](#archive-modification)
- [Archiving](#archiving)

## Reading and extraction
1. Open the file with automatic detection of a format by name or content. In case of successful, an `UnifiedArchive`
   object will be returned. In case of failure - _null_ will be returned.

    ```php
    require 'vendor/autoload.php';
    use wapmorgan\UnifiedArchive\Abilities;
    use wapmorgan\UnifiedArchive\UnifiedArchive;

    $archive = UnifiedArchive::open('filename.rar');
    // or
    $archive = UnifiedArchive::open('filename.zip', null, 'password');
    // or
    $archive = UnifiedArchive::open('filename.tar', [
        Abilities::EXTRACT_CONTENT,
        Abilities::STREAM_CONTENT,
        Abilities::APPEND,
    ]);
    // or
    $archive = UnifiedArchive::open('filename.7z', null, 'password');
    ```

3. Read the list of files of archive or check that file is in archive.

   ```php
   foreach($archive->getFiles() as $filename) { // ['file', 'file2', 'file3', ...]
        // ...
   }
   
   foreach($archive->getFiles('*.txt') as $filename) { // ['README.txt', 'doc.txt', ...]
        // ...
   }
   
   foreach ($archive as $filename) {
        // ...
   }
   
   if ($archive->hasFile('README.md')) {
       // some operations
   }
   ```

4. To get common information about concrete file use `getFileData()` method.
This method returns [an `ArchiveEntry` instance](API.md#ArchiveEntry). 
To get raw file contents use `getFileContent()` method, to get stream for file use `getFileStream()` method.

   ```php
   // file meta
   $file_data = $archive->getFileData('README.md')); // ArchiveEntry with file information
   echo 'Original size is ' . $file_data->uncompressedSize . PHP_EOL;
   echo 'Modification datetime is ' . date('r', $file_data->modificationTime) . PHP_EOL;

   // raw file content
   $file_content = $archive->getFileContent('README.md')); // string

   // pass stream to standard output
   fpassthru($archive->getFileStream('README.md'));
   ```

5. Unpack all archive or specific files on a disk - `extract()`.

    ```php
    // to unpack all contents of archive to "output" folder
    $archive->extract(__DIR__ . '/output');

    // to unpack specific files (README.md and composer.json) from archive to "output" folder
    $archive->extract(__DIR__ . '/output', ['README.md', 'composer.json']);

    // to unpack the "src" catalog with all content from archive into the "sources" catalog on a disk
    $archive->extract(__DIR__ . '/output', 'src/', true);
    ```

## Archive modification
Only few archive formats support modification: (zip, 7z, tar) - it depends on low-level driver - see **Formats** page for details.

1. [Delete files](API.md#UnifiedArchive--delete) from archive

    ```php
    // Delete a single file
    $archive->delete('README.md');

    // Delete multiple files
    $archive->delete(['README.md', 'MANIFEST.MF']);

    // Delete directory with full content
    $archive->delete('src/', true);
    ```

    In case of success the number of successfully deleted files will be returned.

2. [Add files](API.md#UnifiedArchive--add) to archive

    ```php
    // Add a catalog with all contents with full paths
    $archive->add('/var/log/');

    // To add one file (will be stored as one file "/var/log/syslog")
    $archive->add('/var/log/syslog');

    // To add some files or catalogs (all catalogs structure in paths will be kept)
    $archive->add([$directory, $file, $file2, ...]);
    ```

## Archiving
Only few archive formats support modification: zip, 7z, tar (with restrictions).

To pack completely the catalog with all attached files and subdirectories in new archive:

```php
# archive all folder content (with original full name)
UnifiedArchive::create('/var/log', 'Archive.zip');

# archive one file (with original full name)
UnifiedArchive::create('/var/log/syslog', 'Archive.zip');

# archive few files / folders
UnifiedArchive::create([$directory, $file, $file2, ...], 'Archive.zip');
```

Also, there is [extended syntax](API.md#UnifiedArchive--create) for `add()` and `create()`:

```php
UnifiedArchive::create([
   '/var/www/original/name.log',           // file **/var/www/original/name.log** will be store with its original name
   '/var/www/site/runtime/logs',           // directory contents will be stored as '/var/www/site/runtime/logs', with its original name
   'insideArchiveName.log' => '/var/www/original/name.log',   // file /var/www/original/name.log will be stored as insideArchiveName.log in archive root
   'insideArchiveDir' => '/var/www/site/runtime/logs',        // directory contents will be stored in insideArchiveDir dir inside archive
   '' => ['/home/user1/docs', '/home/user2/docs'],            // directories user1 and user2 docs contents will be merged and stored in archive root
], 'archive.zip');
```
