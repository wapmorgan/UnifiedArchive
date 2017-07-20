<?php
namespace wapmorgan\UnifiedArchive;

use Exception;

class CamApplication {
    protected function open($file) {
        $archive = UnifiedArchive::open($file);
        if ($archive === null) throw new Exception('Could not open archive '.$file);
        return $archive;
    }

    public function listArray($args) {
        $archive = $this->open($args['ARCHIVE']);
        foreach ($archive->getFileNames() as $file) {
            echo $file.PHP_EOL;
        }
    }

    public function table($args) {
        $archive = $this->open($args['ARCHIVE']);
        $dirs = array();
        echo sprintf('%51s | %4s | %-18s'.PHP_EOL, 'File name', 'Size', 'Date');
        echo str_repeat('-', 80).PHP_EOL;
        foreach ($archive->getFileNames() as $file) {
            $info = $archive->getFileData($file);
            $size = $this->formatSize($info->uncompressed_size);
            $file_name = strlen($file) > 51 ? substr($file, 0, 49).'..' : $file;
            echo sprintf('%-51s | %1.1f%s | %18s'.PHP_EOL,
                $file_name,
                $size[0],
                $size[1],
                $this->formatDate($info->mtime)
                );
        }
        $size = $this->formatSize($archive->countUncompressedFilesSize());
        $packed_size = $this->formatSize($archive->countCompressedFilesSize());
        echo str_repeat('-', 80).PHP_EOL;
        echo sprintf('%51s | %1.1f%s | %1.1f%s'.PHP_EOL, 'Total '.$archive->countFiles().' file(s)', $size[0], $size[1], $packed_size[0], $packed_size[1]);

    }

    public function formatSize($bytes, $precision = 1) {
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

    public function formatDate($unixtime) {
        if ((mktime(0, 0, 0) - $unixtime) < 86000)
            return 'Today, '.date('G:m', $unixtime);
        else if ((strtotime('yesterday') - $unixtime) < 172000)
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

    public function info($args) {
        $archive = $this->open($args['ARCHIVE']);
        echo 'Archive           changed: '.$this->formatDate(filemtime($args['ARCHIVE'])).PHP_EOL;
        echo 'Archive          contains: '.$archive->countFiles().' file'.($archive->countFiles() > 1 ? 's' : null).PHP_EOL;
        echo 'Archive   compressed size: '.implode(' ', $this->formatSize($archive->countCompressedFilesSize(), 2)).PHP_EOL;
        echo 'Archive uncompressed size: '.implode(' ', $this->formatSize($archive->countUncompressedFilesSize(), 2)).PHP_EOL;
        echo 'Archive compression ratio: '.round($archive->countUncompressedFilesSize() / $archive->countCompressedFilesSize(), 6).'/1'.PHP_EOL;
    }

    public function extract($args) {
        $archive = $this->open($args['ARCHIVE']);
        $output = getcwd();
        if (isset($args['--output']))
            $output = $args['--output'];
        if (empty($args['FILES_IN_ARCHIVE']) || $args['FILES_IN_ARCHIVE'] == array('/') || $args['FILES_IN_ARCHIVE'] == array('*'))
            $archive->extractNode($output);
        else {
            foreach ($args['FILES_IN_ARCHIVE'] as $file) {
                $archive->extractNode($output, $file);
            }
        }
    }

    public function printFile($args) {
        $archive = $this->open($args['ARCHIVE']);
        foreach ($args['FILES_IN_ARCHIVE'] as $file) {
            $info = $archive->getFileData($file);
            if ($info === false) {
                echo 'File '.$file.' IS NOT PRESENT'.PHP_EOL;
                continue;
            }
            echo 'File content: '.$file.' (size is '.implode('', $this->formatSize($info->compressed_size, 1)).')'.PHP_EOL;
            echo $archive->getFileContent($file).PHP_EOL;
        }
    }

    public function details($args) {
        $archive = $this->open($args['ARCHIVE']);
        foreach ($args['FILES_IN_ARCHIVE'] as $file) {
            $info = $archive->getFileData($file);
            if ($info === false) {
                echo 'File '.$file.' IS NOT PRESENT'.PHP_EOL;
                continue;
            }
            echo 'File name        : '.$file.PHP_EOL;
            echo 'Compressed size  : '.implode('', $this->formatSize($info->compressed_size, 2)).PHP_EOL;
            echo 'Uncompressed size: '.implode('', $this->formatSize($info->uncompressed_size, 2)).PHP_EOL;
            echo 'Is compressed    : '.($info->is_compressed ? 'yes' : 'no').PHP_EOL;
            echo 'Date modification: '.$this->formatDate($info->mtime).PHP_EOL;
        }
    }

    public function delete($args) {
        $archive = $this->open($args['ARCHIVE']);
        $files = $archive->getFileNames();
        foreach ($args['FILES_IN_ARCHIVE'] as $file) {
            if (!in_array($file, $files)) {
                echo 'File '.$file.' is NOT in archive'.PHP_EOL;
                continue;
            }
            $archive->deleteFiles($file);
        }
    }

    public function add($args) {
        $archive = $this->open($args['ARCHIVE']);
        foreach ($args['FILES_ON_DISK'] as $file) {
            $archive->addFiles($file);
        }
    }

    public function create($args) {
        $archived_files = UnifiedArchive::archiveNodes($args['FILES_ON_DISK'], $args['ARCHIVE']);
        echo 'Created archive '.$args['ARCHIVE'].' with '.$archived_files.' file(s) of total size '.implode('', $this->formatSize(filesize($args['ARCHIVE']))).PHP_EOL;
    }
}
