<?php
namespace wapmorgan\UnifiedArchive;

use Exception;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class CamApplication {
    /**
     * @param $file
     * @param null $password
     * @return UnifiedArchive
     * @throws Exceptions\UnsupportedOperationException
     * @throws Exception
     */
    protected function open($file, $password = null)
    {
        if (!UnifiedArchive::canOpen($file))
            throw new Exception('Could not open archive '.$file.'. Try installing suggested packages or run `cam -f` to see formats support.');

        $archive = UnifiedArchive::open($file, $password);
        if ($archive === null)
            throw new Exception('Could not open archive '.$file);

        return $archive;
    }

    /**
     *
     */
    public function checkFormats()
    {
        echo "format\topen\tstream\tcreate\tappend\tupdate\tencrypt\tdrivers".PHP_EOL;
        foreach(Formats::getFormatsReport() as $format => $config) {
            echo $format."\t"
                .($config['open'] ? '+' : '-')."\t"
                .($config['stream'] ? '+' : '-')."\t"
                .($config['create'] ? '+' : '-')."\t"
                .($config['append'] ? '+' : '-')."\t"
                .($config['update'] ? '+' : '-')."\t"
                .($config['encrypt'] ? '+' : '-')."\t"
                .implode(', ', array_map(function($val) { return substr($val, strrpos($val, '\\') + 1); }, $config['drivers'])).PHP_EOL;
        }
    }

    public function checkDrivers()
    {
        $notInstalled = [];

        /** @var BasicDriver $driverClass */
        $i = 1;
        foreach (Formats::$drivers as $driverClass) {
            $description = $driverClass::getDescription();
            $install = $driverClass::getInstallationInstruction();
            if (!empty($install)) {
                $notInstalled[] = [$driverClass, $description, $install];
            } else {
                echo ($i++) . '. ' . $driverClass . ' - ' . $description . PHP_EOL;
            }
        }

        if (!empty($notInstalled)) {
            echo PHP_EOL.'Not installed:'.PHP_EOL;
            $i = 1;
            foreach ($notInstalled as $data) {
                echo ($i++) . '. ' . $data[0] . ' - ' . $data[1] . PHP_EOL
                    . '- ' . $data[2] . PHP_EOL.PHP_EOL;
            }
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function listArray($args)
    {
        $filter = isset($args['FILTER']) ? $args['FILTER'] : null;
        $archive = $this->open($args['ARCHIVE']);
        foreach ($archive->getFileNames($filter) as $file) {
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
        $filter = isset($args['FILTER']) ? $args['FILTER'] : null;

        $width = $this->getTerminalWidth();
        $name_width = $width - 44;

        echo sprintf('%'.$name_width.'s | %8s | %8s | %-18s'.PHP_EOL, 'File name', '#Size', 'Size', 'Date');
        echo str_repeat('-', $width).PHP_EOL;
        foreach ($archive->getFileNames($filter) as $file) {
            $info = $archive->getFileData($file);
            $file_name = strlen($file) > $name_width ? substr($file, 0, $name_width-2).'..' : $file;
            echo sprintf('%-'.$name_width.'s | %8s | %8s | %18s'.PHP_EOL,
                $file_name,
                implode(null, $this->formatSize($info->compressedSize, 3)),
                implode(null, $this->formatSize($info->uncompressedSize, 3)),
                $this->formatDate($info->modificationTime)
                );
        }
        $size = $this->formatSize($archive->getOriginalSize());
        $packed_size = $this->formatSize($archive->getCompressedSize());
        echo str_repeat('-', $width).PHP_EOL;
        echo sprintf('%'.$name_width.'s | %8s | %8s'.PHP_EOL, 'Total '.$archive->countFiles().' file(s)', $packed_size[0].$packed_size[1], $size[0].$size[1]);

    }

    /**
     * @param $bytes
     * @param int $precision
     * @return array
     */
    public function formatSize($bytes, $precision = 2)
    {
        $units = ['b', 'k', 'm', 'g', 't'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        $i = round($bytes, $precision);
        if ($precision == 1 && $i >= 10) {
            $i = round($i / 1024, 1);
            $pow++;
        }

        return [$i, $units[$pow]];
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
        echo 'Archive              type: '.$archive->getFormat().PHP_EOL;
        echo 'Archive           changed: '.$this->formatDate(filemtime($args['ARCHIVE'])).PHP_EOL;
        echo 'Archive          contains: '.$archive->countFiles().' file'.($archive->countFiles() > 1 ? 's' : null).PHP_EOL;
        echo 'Archive   compressed size: '.implode(' ', $this->formatSize($archive->getCompressedSize(), 2)).PHP_EOL;
        echo 'Archive uncompressed size: '.implode(' ', $this->formatSize($archive->getOriginalSize(), 2)).PHP_EOL;
        echo 'Archive compression ratio: '.round($archive->getOriginalSize() / $archive->getCompressedSize(), 6).'/1 ('.floor($archive->getCompressedSize() / $archive->getOriginalSize() * 100).'%)'.PHP_EOL;
        if (($comment = $archive->getComment()) !== null)
            echo 'Archive           comment: '.$comment.PHP_EOL;
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function extract($args)
    {
        $archive = $this->open($args['ARCHIVE'], isset($args['--password']) ? $args['--password'] : null);
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
            if ($extracted > 0) echo 'Extracted '.$extracted.' file(s) to '.$output.PHP_EOL;
        }
    }

    /**
     * @param $args
     * @throws Exception
     * @throws \Archive7z\Exception
     */
    public function printFile($args)
    {
        $archive = $this->open($args['ARCHIVE'], isset($args['--password']) ? $args['--password'] : null);
        foreach ($args['FILES_IN_ARCHIVE'] as $file) {
            if (!$archive->hasFile($file)) {
                echo 'File '.$file.' IS NOT PRESENT'.PHP_EOL;
                exit(-1);
            }
//            $info = $archive->getFileData($file);
//            echo 'File content: '.$file.' (size is '.implode('', $this->formatSize($info->uncompressedSize, 1)).')'.PHP_EOL;
            echo $archive->getFileContent($file);
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
            $comment = $info->comment;
            if ($comment !== null)
                echo 'Comment: '.$comment.PHP_EOL;
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
     * @throws \Archive7z\Exception
     */
    public function addFromStdin($args)
    {
        $archive = $this->open($args['ARCHIVE']);
        $content = null;
        while (!feof(STDIN)) {
            $content .= fgets(STDIN);
        }
        $len = strlen($content);

        $added_files = $archive->addFileFromString($args['FILE_IN_ARCHIVE'], $content);
        if ($added_files === false)
            echo 'Error'.PHP_EOL;
        else {
            $size = $this->formatSize($len);
            echo sprintf('Added %s(%1.1f%s) file to %s',
                    $args['FILE_IN_ARCHIVE'], $size[0], $size[1], $args['ARCHIVE']) . PHP_EOL;
        }
    }

    /**
     * @param $args
     * @throws Exception
     */
    public function create($args)
    {
        $password = isset($args['--password']) ? $args['--password'] : null;
        $compression_level = isset($args['--compressionLevel']) ? $args['--compressionLevel'] : BasicDriver::COMPRESSION_AVERAGE;

        if (file_exists($args['ARCHIVE'])) {
            if (is_dir($args['ARCHIVE']))
                echo $args['ARCHIVE'].' is a directory!'.PHP_EOL;
            else {
                echo 'File '.$args['ARCHIVE'].' already exists!'.PHP_EOL;
            }
        } else {
            $files = [];
            $is_absolute = $args['--path'] === 'absolute';

            foreach ($args['FILES_ON_DISK'] as $i => $file) {
                $file = realpath($file);
                if ($is_absolute) {
                    $files[] = $file;
                } else {
                    $files[basename($file)] = $file;
                }
            }

            $archived_files = UnifiedArchive::archiveFiles($files, $args['ARCHIVE'], $compression_level, $password);
            if ($archived_files === false)
                echo 'Error'.PHP_EOL;
            else {
                if (isset($args['--comment'])) {
                    $archive = UnifiedArchive::open($args['ARCHIVE']);
                    $archive->setComment($args['--comment']);
                }
                echo 'Created archive ' . $args['ARCHIVE'] . ' with ' . $archived_files . ' file(s) of total size ' . implode('', $this->formatSize(filesize($args['ARCHIVE']))) . PHP_EOL;
            }
        }
    }

    public function createFake($args)
    {
        $files = [];
        $is_absolute = $args['--path'] === 'absolute';

        foreach ($args['FILES_ON_DISK'] as $i => $file) {
            $file = realpath($file);
            if ($is_absolute) {
                $files[] = $file;
            } else {
                $files[basename($file)] = $file;
            }
        }

        var_dump(UnifiedArchive::prepareForArchiving($files, $args['ARCHIVE']));
    }

    protected function getTerminalWidth()
    {
        if (is_numeric($columns = trim(getenv('COLUMNS')))) {
            return $columns;
        }

        if (function_exists('shell_exec')) {
            // try for bash
            if (is_numeric($bash_width = trim(shell_exec('tput cols'))))
                return $bash_width;

            // try for windows
            if (!empty($win_width_val = trim(shell_exec('mode con'))) && preg_match('~columns: (\d+)~i', $win_width_val, $win_width))
                return $win_width[1];
        }

        return 80;
    }
}
