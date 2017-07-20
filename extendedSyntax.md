Despite that the class is intended for reading and unpacking of archives, I
added the flexible and simple mechanism of packing of files.

All that it is necessary for archive creation - to transfer the list of *nodes*
(a source, a destination, parameters), a name of created archive and archive
type.

All functions of packing are carried out by the static archiveNodes method. The
most important argument of a method - the list of notes for archiving. Having
reconsidered ways of creation of different types of archives in Php (
**ZipArchive** :: *addFile($filename, $localname)*,
**Archive_Tar** :: *addModify($filename, $add_dir, $remove_dir)*), I came to a
conclusion that it is most convenient to transfer sets of paths in which the
path of an initial node (a node on a disk) and a path of a node of archive (a
node is stored in archive).

It is possible to add the simple file the following node (by the way, a node - a
simple array, with the fields `source`, `destination`):
```php
array('source' => '/etc/php5/fpm/php.ini', 'destination' => 'php.ini'),
// We add Php configuration from the catalog of system settings in an archive
root
```
But file addition on one very boring occupation therefore I added two
possibilities of definition of the catalog as a source.

The first option allows to add any catalog in other catalog in archive.

For example, we want to add couple of catalogs with numerical names in the
catalog with a name "software versions".
```php
array('source' => '/home/.../Dropbox/software/1/',
    'destination' => 'SoftwareVersions/'),
    // the first version of the program will be kept as "SoftwareVersions/1/"
array('source' => '/home/.../Dropbox/software/2/',
    'destination' => 'SoftwareVersions/')
    // the following version of the program will be kept by a row in
    // "SoftwareVersions/2/"
```

Conveniently, isn't that so? In such a way it is possible to keep big catalogs
one line of a configuration.

And what to do if for example in the "software/1/" catalog there are internal
folders which we too want to keep in archive? Then it is possible to add
parameter "recursively" to a node. The configuration will look so:
```php
array('source' => '/home/.../Dropbox/software/1/',
    'destination' => 'SoftwareVersions/', 'recursive' => true),
    // to add the first version with subdirectories
array('source' => '/home/.../Dropbox/software/2/',
    'destination' => 'SoftwareVersions/', 'recursive' => true)
    // to add the second version with subdirectories
```

But what if it is necessary to copy simply all files from the catalog in
archive?  To make it as it is simple: after a name catalogs in the field of a
source it is necessary to add only an asterisk (*).  Let's say we want to
archive all pictures of cats which "are smeared" on our file system, thus to
place them everything the catalog of images.

It is possible to make it the following configuration:
```php
//array('source' => 'pictures/other/cats/*', 'destination' => 'Pictures/'),
// to add cats from the current directory
//array('source' => '~/Desktop/catties/*', 'destination' => 'Pictures/'),
// to add cats from the home catalog
//array('source' => '/media/.../W44XX33YY22ZZ111/Cats/*',
    'destination' => 'Pictures/') // to add cats from an external hard disk
array('source' => '/var/log/*', 'destination' => 'logs/', 'recursive' => true)
```

Remarkably! Now everything that we collected for years, it is possible command
to keep one in archive.
```php
$nodes = array(
	array('source' => '/etc/php5/fpm/php.ini', 'destination' => 'php.ini'),
	array('source' => '/home/.../Dropbox/software/1/',
         'destination' => 'SoftwareVersions/', 'recursive' => true),
	array('source' => '/home/.../Dropbox/software/2/',
         'destination' => 'SoftwareVersions/', 'recursive' => true),
	//array('source' => 'pictures/other/cats/*', 'destination' => 'Pictures/'),
	//array('source' => '~/Desktop/catties/*', 'destination' => 'Pictures/'),
	//array('source' => '/media/.../W44XX33YY22ZZ111/Cats/*',
         'destination' => 'Pictures/'),
	array('source' => '/var/log/*', 'destination' => 'logs/',
        'recursive' => true),
);
UnifiedArchive::archiveNodes($nodes, 'Archive.zip');
// or
UnifiedArchive::archiveNodes($nodes, 'Archive.gz'); // notice that you can pack
                                                    // no more than one file
                                                    // into such type of archive
// or
UnifiedArchive::archiveNodes($nodes, 'Archive.tar');
// or
UnifiedArchive::archiveNodes($nodes, 'Archive.tar.gz'); // Be careful,
                                                        // compression is very
                                                        // resource-intensive
// or
UnifiedArchive::archiveNodes($nodes, 'Archive.tar.bz2'); // here too
                                                         // intelligently
```
