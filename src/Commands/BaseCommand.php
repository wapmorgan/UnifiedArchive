<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class BaseCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $abilitiesLabels = [
        'open' => Formats::OPEN,
        'open (+password)' => Formats::OPEN_ENCRYPTED,
        'get comment' => Formats::GET_COMMENT,
        'extract' => Formats::EXTRACT_CONTENT,
        'stream' => Formats::STREAM_CONTENT,
        'append' => Formats::APPEND,
        'delete' => Formats::DELETE,
        'set comment' => Formats::SET_COMMENT,
        'create' => Formats::CREATE,
        'create (+password)' => Formats::CREATE_ENCRYPTED,
    ];

    protected static $abilitiesShortCuts = [
        Formats::OPEN => 'o',
        Formats::OPEN_ENCRYPTED => 'O',
        Formats::GET_COMMENT => 't',
        Formats::EXTRACT_CONTENT => 'x',
        Formats::STREAM_CONTENT => 's',
        Formats::APPEND => 'a',
        Formats::DELETE => 'd',
        Formats::SET_COMMENT => 'T',
        Formats::CREATE => 'c',
        Formats::CREATE_ENCRYPTED => 'C',
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

        $archive = UnifiedArchive::open($file, $password);
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
}
