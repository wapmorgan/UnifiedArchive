<?php
namespace wapmorgan\UnifiedArchive;

abstract class BasicArchive implements AbstractArchive
{
    /**
     * @param $outputFolder
     * @param string|array|null $files
     * @deprecated 0.1.0
     * @see extractFiles()
     * @return bool|int
     */
    public function extractNode($outputFolder, $files = null)
    {
        return $this->extractFiles($outputFolder, $files);
    }

    /**
     * @param $filesOrFiles
     * @param $archiveName
     * @deprecated 0.1.0
     * @see archiveFiles()
     * @return mixed
     */
    public static function archiveNodes($filesOrFiles, $archiveName)
    {
        return static::archiveFiles($filesOrFiles, $archiveName);
    }

    /**
     * Expands files list
     * @param $archiveFiles
     * @param $files
     * @return array
     */
    protected static function expandFileList($archiveFiles, $files)
    {
        $newFiles = [];
        foreach ($files as $file) {
            foreach ($archiveFiles as $archiveFile) {
                if (fnmatch($file.'*', $archiveFile))
                    $newFiles[] = $archiveFile;
            }
        }
        return $newFiles;
    }

    /**
     * @param string|array $nodes
     * @return array|bool
     */
    protected static function createFilesList($nodes)
    {
        $files = array();

        // passed an extended list
        if (is_array($nodes)) {
            foreach ($nodes as $source => $destination) {
                if (is_numeric($source))
                    $source = $destination;

                $destination = rtrim($destination, '/\\*');

                // if is directory
                if (is_dir($source))
                    self::importFilesFromDir(rtrim($source, '/\\*').'/*',
                        !empty($destination) ? $destination.'/' : null, true, $files);
                else if (is_file($source))
                    $files[$destination] = $source;
            }

        } else if (is_string($nodes)) { // passed one file or directory
            // if is directory
            if (is_dir($nodes))
                self::importFilesFromDir(rtrim($nodes, '/\\*').'/*', null, true,
                    $files);
            else if (is_file($nodes))
                $files[basename($nodes)] = $nodes;
        }

        return $files;
    }

    /**
     * @param string $source
     * @param string|null $destination
     * @param bool $recursive
     * @param array $map
     */
    protected static function importFilesFromDir($source, $destination, $recursive, &$map)
    {
        // $map[$destination] = rtrim($source, '/*');
        // do not map root archive folder

        if ($destination !== null)
            $map[$destination] = null;

        foreach (glob($source, GLOB_MARK) as $node) {
            if (in_array(substr($node, -1), ['/', '\\'], true) && $recursive) {
                self::importFilesFromDir(str_replace('\\', '/', $node).'*',
                    $destination.basename($node).'/', $recursive, $map);
            } elseif (is_file($node) && is_readable($node)) {
                $map[$destination.basename($node)] = $node;
            }
        }
    }

    /**
     * @param string $file
     * @param string $archiveName
     * @return bool
     */
    static public function archiveFile($file, $archiveName)
    {
        if (!is_file($file))
            throw new \InvalidArgumentException($file.' is not a valid file to archive');

        return static::archiveFiles($file, $archiveName) === 1;
    }

    /**
     * @param string $directory
     * @param string $archiveName
     * @return bool
     */
    static public function archiveDirectory($directory, $archiveName)
    {
        if (!is_dir($directory) || !is_readable($directory))
            throw new \InvalidArgumentException($directory.' is not a valid directory to archive');

        return static::archiveFiles($directory, $archiveName) > 0;
    }

    /**
     * @param string $file
     * @param string|null $inArchiveName
     * @return bool
     */
    public function addFile($file, $inArchiveName = null)
    {
        if (!is_file($file))
            throw new \InvalidArgumentException($file.' is not a valid file to add in archive');

        return ($inArchiveName !== null
            ? $this->addFiles([$file => $inArchiveName])
            : $this->addFiles([$file])) === 1;
    }

    /**
     * @param string $directory
     * @param string|null $inArchivePath
     * @return bool
     */
    public function addDirectory($directory, $inArchivePath = null)
    {
        if (!is_dir($directory) || !is_readable($directory))
            throw new \InvalidArgumentException($directory.' is not a valid directory to add in archive');

        return ($inArchivePath !== null
                ? $this->addFiles([$directory => $inArchivePath])
                : $this->addFiles([$inArchivePath])) > 0;
    }
}
