<?php
namespace wapmorgan\UnifiedArchive;

use Exception;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class CamApplication {
    /**
     * @param $file
     * @return UnifiedArchive
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    protected function open($file)
    {
        if (!UnifiedArchive::canOpenArchive($file))
            throw new Exception('Could not open archive '.$file.'. Try installing suggested packages or run `cam -f` to see formats support.');

        $archive = UnifiedArchive::open($file);
        if ($archive === null)
            throw new Exception('Could not open archive '.$file);

        return $archive;
    }

    /**
     *
     */
    public function checkFormats()
    {
        $types = [
            '.zip' => [UnifiedArchive::canOpenType(UnifiedArchive::ZIP), 'install "zip" extension'],
            '.rar' => [UnifiedArchive::canOpenType(UnifiedArchive::RAR), 'install "rar" extension'],
            '.gz' => [UnifiedArchive::canOpenType(UnifiedArchive::GZIP), 'install "zlib" extension'],
            '.bz2' => [UnifiedArchive::canOpenType(UnifiedArchive::BZIP), 'install "bz2" extension'],
            '.xz' => [UnifiedArchive::canOpenType(UnifiedArchive::LZMA), 'install "xz" extension'],
            '.7z' => [UnifiedArchive::canOpenType(UnifiedArchive::SEVEN_ZIP), 'install "gemorroj/archive7z" package'],
            '.iso' => [UnifiedArchive::canOpenType(UnifiedArchive::ISO), 'install "phpclasses/php-iso-file" package'],
            '.cab' => [UnifiedArchive::canOpenType(UnifiedArchive::CAB), 'install "wapmorgan/cab-archive" package'],

            '.tar' => [UnifiedArchive::canOpenType(UnifiedArchive::TAR), 'install "phar" extension or "pear/archive_tar" package'],
            '.tar.gz' => [UnifiedArchive::canOpenType(UnifiedArchive::TAR_GZIP), 'install "phar" extension or "pear/archive_tar" package and "zlib" extension'],
            '.tar.bz2' => [UnifiedArchive::canOpenType(UnifiedArchive::TAR_BZIP), 'install "phar" extension or "pear/archive_tar" package and "bz2" extension'],
            '.tar.xz' => [UnifiedArchive::canOpenType(UnifiedArchive::TAR_LZMA), 'install "pear/archive_tar" package and "xz" extension'],
            '.tar.Z' => [UnifiedArchive::canOpenType(UnifiedArchive::TAR_LZW), 'install "pear/archive_tar" package and "compress" system utility'],
        ];

        $installed = $not_installed = [];

        foreach ($types as $extension => $configuration) {
            if ($configuration[0]) {
                $installed[] = $extension;
            } else {
                $not_installed[$extension] = $configuration[1];
            }
        }

        if (!empty($installed)) {
            echo 'Supported archive types: '.implode(', ', $installed).PHP_EOL;
        }

        if (!empty($not_installed)) {
            echo 'Not supported archive types:'.PHP_EOL;
            array_walk($not_installed, function ($instruction, $extension) {
                echo '- '.$extension.': '.$instruction.PHP_EOL;
            });
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function listArray($args)
    {
        $archive = $this->open($args['ARCHIVE']);
        foreach ($archive->getFileNames() as $file) {
            echo $file.PHP_EOL;
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function table($args)
    {
        $archive = $this->open($args['ARCHIVE']);

        echo sprintf('%51s | %4s | %-18s'.PHP_EOL, 'File name', 'Size', 'Date');
        echo str_repeat('-', 80).PHP_EOL;
        foreach ($archive->getFileNames() as $file) {
            $info = $archive->getFileData($file);
            $size = $this->formatSize($info->uncompressedSize);
            $file_name = strlen($file) > 51 ? substr($file, 0, 49).'..' : $file;
            echo sprintf('%-51s | %1.1f%s | %18s'.PHP_EOL,
                $file_name,
                $size[0],
                $size[1],
                $this->formatDate($info->modificationTime)
                );
        }
        $size = $this->formatSize($archive->countUncompressedFilesSize());
        $packed_size = $this->formatSize($archive->countCompressedFilesSize());
        echo str_repeat('-', 80).PHP_EOL;
        echo sprintf('%51s | %1.1f%s | %1.1f%s'.PHP_EOL, 'Total '.$archive->countFiles().' file(s)', $size[0], $size[1], $packed_size[0], $packed_size[1]);

    }

    /**
     * @param $bytes
     * @param int $precision
     * @return array
     */
    public function formatSize($bytes, $precision = 1)
    {
        $units = array('b', 'k', 'm', 'g', 't');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        $i = round($bytes, $precision);
        if ($precision == 1 && $i >= 10) {
            $i = round($i / 1024, 1);
            $pow++;
        }

        return array($i, $units[$pow]);
    }

    /**
     * @param $unixtime
     *
     * @return string
     * @throws \Exception
     */
    public function formatDate($unixtime)
    {
        if (strtotime('today') < $unixtime)
            return 'Today, '.date('G:m', $unixtime);
        else if (strtotime('yesterday') < $unixtime)
            return 'Yesterday, '.date('G:m', $unixtime);
        else {
            $datetime = new \DateTime();
            $datetime->setTimestamp($unixtime);
            if ($datetime->format('Y') == date('Y'))
                return $datetime->format('d M, G:m');
            else
                return $datetime->format('d M Y, G:m');
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function info($args)
    {
        $archive = $this->open($args['ARCHIVE']);
        echo 'Archive              type: '.$archive->getArchiveType().PHP_EOL;
        echo 'Archive           changed: '.$this->formatDate(filemtime($args['ARCHIVE'])).PHP_EOL;
        echo 'Archive          contains: '.$archive->countFiles().' file'.($archive->countFiles() > 1 ? 's' : null).PHP_EOL;
        echo 'Archive   compressed size: '.implode(' ', $this->formatSize($archive->countCompressedFilesSize(), 2)).PHP_EOL;
        echo 'Archive uncompressed size: '.implode(' ', $this->formatSize($archive->countUncompressedFilesSize(), 2)).PHP_EOL;
        echo 'Archive compression ratio: '.round($archive->countUncompressedFilesSize() / $archive->countCompressedFilesSize(), 6).'/1 ('.floor($archive->countCompressedFilesSize() / $archive->countUncompressedFilesSize() * 100).'%)'.PHP_EOL;
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function extract($args)
    {
        $archive = $this->open($args['ARCHIVE']);
        $output = getcwd();
        if (isset($args['--output'])) {
            if (!is_dir($args['--output']))
                mkdir($args['--output']);
            $output = realpath($args['--output']);
        }

        if (empty($args['FILES_IN_ARCHIVE']) || $args['FILES_IN_ARCHIVE'] == array('/') || $args['FILES_IN_ARCHIVE'] == array('*')) {
            $result = $archive->extractFiles($output);
            if ($result === false) echo 'Error occured'.PHP_EOL;
            else echo 'Extracted '.$result.' file(s) to '.$output.PHP_EOL;
        } else {
            $extracted = 0;
            $errored = [];
            foreach ($args['FILES_IN_ARCHIVE'] as $file) {
                $result = $archive->extractFiles($output, $file);
                if ($result === false) $errored[] = $file;
                else $extracted += $result;
            }
            if (!empty($errored)) echo 'Errored: '.implode(', ', $errored).PHP_EOL;
            if ($extracted > 0) echo 'Exctracted '.$extracted.' file(s) to '.$output.PHP_EOL;
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function printFile($args)
    {
        $archive = $this->open($args['ARCHIVE']);
        foreach ($args['FILES_IN_ARCHIVE'] as $file) {
            $info = $archive->getFileData($file);
            if ($info === false) {
                echo 'File '.$file.' IS NOT PRESENT'.PHP_EOL;
                continue;
            }
            echo 'File content: '.$file.' (size is '.implode('', $this->formatSize($info->uncompressedSize, 1)).')'.PHP_EOL;
            echo $archive->getFileContent($file).PHP_EOL;
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function details($args)
    {
        $archive = $this->open($args['ARCHIVE']);
        foreach ($args['FILES_IN_ARCHIVE'] as $file) {
            $info = $archive->getFileData($file);
            if ($info === false) {
                echo 'File '.$file.' IS NOT PRESENT'.PHP_EOL;
                continue;
            }
            echo 'File name        : '.$file.PHP_EOL;
            echo 'Compressed size  : '.implode('', $this->formatSize($info->compressedSize, 2)).PHP_EOL;
            echo 'Uncompressed size: '.implode('', $this->formatSize($info->uncompressedSize, 2)).PHP_EOL;
            echo 'Is compressed    : '.($info->isCompressed ? 'yes' : 'no').PHP_EOL;
            echo 'Date modification: '.$this->formatDate($info->modificationTime).PHP_EOL;
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function delete($args)
    {
        $archive = $this->open($args['ARCHIVE']);
        $files = $archive->getFileNames();
        foreach ($args['FILES_IN_ARCHIVE'] as $file) {
            if (!in_array($file, $files)) {
                echo 'File '.$file.' is NOT in archive'.PHP_EOL;
                continue;
            }
            if ($archive->deleteFiles($file) === false)
                echo 'Error file '.$file.PHP_EOL;
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function add($args)
    {
        $archive = $this->open($args['ARCHIVE']);
        $added_files = $archive->addFiles($args['FILES_ON_DISK']);
        if ($added_files === false)
            echo 'Error'.PHP_EOL;
        else
            echo 'Added '.$added_files.' file(s)'.PHP_EOL;
    }

    /**
     * @param $args
     * @throws Exception
     */
    public function create($args)
    {
        if (file_exists($args['ARCHIVE'])) {
            if (is_dir($args['ARCHIVE']))
                echo $args['ARCHIVE'].' is a directory!'.PHP_EOL;
            else {
                echo 'File '.$args['ARCHIVE'].' already exists!'.PHP_EOL;
            }
        } else {
            $archived_files = UnifiedArchive::archiveFiles($args['FILES_ON_DISK'], $args['ARCHIVE']);
            if ($archived_files === false)
                echo 'Error'.PHP_EOL;
            else
                echo 'Created archive ' . $args['ARCHIVE'] . ' with ' . $archived_files . ' file(s) of total size ' . implode('', $this->formatSize(filesize($args['ARCHIVE']))) . PHP_EOL;
        }
    }
}
