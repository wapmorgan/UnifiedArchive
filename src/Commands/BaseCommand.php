<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class BaseCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $abilitiesLabels = [
        'open' => BasicDriver::OPEN,
        'open (+password)' => BasicDriver::OPEN_ENCRYPTED,
        'get comment' => BasicDriver::GET_COMMENT,
        'extract' => BasicDriver::EXTRACT_CONTENT,
        'stream' => BasicDriver::STREAM_CONTENT,
        'append' => BasicDriver::APPEND,
        'delete' => BasicDriver::DELETE,
        'set comment' => BasicDriver::SET_COMMENT,
        'create' => BasicDriver::CREATE,
        'create (+password)' => BasicDriver::CREATE_ENCRYPTED,
        'create (as string)' => BasicDriver::CREATE_IN_STRING,
    ];

    protected static $abilitiesShortCuts = [
        BasicDriver::OPEN => 'o',
        BasicDriver::OPEN_ENCRYPTED => 'O',
        BasicDriver::GET_COMMENT => 't',
        BasicDriver::EXTRACT_CONTENT => 'x',
        BasicDriver::STREAM_CONTENT => 's',
        BasicDriver::APPEND => 'a',
        BasicDriver::DELETE => 'd',
        BasicDriver::SET_COMMENT => 'T',
        BasicDriver::CREATE => 'c',
        BasicDriver::CREATE_ENCRYPTED => 'C',
        BasicDriver::CREATE_IN_STRING => 'S',
    ];

    /**
     * @param $file
     * @param null $password
     * @return UnifiedArchive
     * @throws \Exception
     */
    protected function open($file, $password = null)
    {
        if (!UnifiedArchive::canOpen($file))
            throw new \Exception('Could not open archive '.$file.'. Try installing suggested packages or run `cam -f` to see formats support.');

        $archive = UnifiedArchive::open($file, [], $password);
        if ($archive === null)
            throw new \Exception('Could not open archive '.$file);

        return $archive;
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
                return $datetime->format('d M, H:m');
            else
                return $datetime->format('d M Y, H:m');
        }
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
     * @param $stream
     * @return false|string
     */
    protected function getMimeTypeByStream($stream)
    {
        return @mime_content_type($stream);
    }

    protected function getDriverBaseName($driverClass)
    {
        return substr($driverClass, strrpos($driverClass, '\\') + 1);
    }

    protected function resolveDriverName($driver)
    {
        if (strpos($driver, '\\') === false) {
            if (class_exists('\\wapmorgan\\UnifiedArchive\\Drivers\\' . $driver)) {
                $driver = '\\wapmorgan\\UnifiedArchive\\Drivers\\' . $driver;
            } else if (class_exists('\\wapmorgan\\UnifiedArchive\\Drivers\\OneFile\\' . $driver)) {
                $driver = '\\wapmorgan\\UnifiedArchive\\Drivers\\OneFile\\' . $driver;
            }
        }
        if ($driver[0] !== '\\') {
            $driver = '\\'.$driver;
        }
        return $driver;
    }
}
