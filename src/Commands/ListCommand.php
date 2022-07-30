<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'files:list';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Lists archive entries')
            ->setHelp('Lists archive entries.')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Files filter (as for fnmatch). If no * passed in filter, it will be added at the end of filter')
            ->addOption('longFormat', 'l', InputOption::VALUE_NONE, 'Use long format')
            ->addOption('human-readable-size', null, InputOption::VALUE_NONE, 'Use human-readable size')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $archive = $this->getArchive($input, $output);
        $filter = $input->getArgument('filter');
        $long_format = $input->getOption('longFormat');
        $human_readable_size = $input->getOption('human-readable-size');

        if (!empty($filter) && strpos($filter, '*') === false) {
            $filter .= '*';
        }

        if ($long_format) {
            $uncomp_size_length = $comp_size_length = 0;
            foreach ($archive->getFiles($filter) as $file) {
                $details = $archive->getFileData($file);

                if ($human_readable_size) {
                    $un_comp_size = implode($this->formatSize($details->uncompressedSize));
                    $comp_size = implode($this->formatSize($details->compressedSize));
                } else {
                    $un_comp_size = $details->uncompressedSize;
                    $comp_size = $details->compressedSize;
                }

                $len = strlen($un_comp_size);
                if ($len > $uncomp_size_length) {
                    $uncomp_size_length = $len;
                }
                $len = strlen($comp_size);
                if ($len > $comp_size_length) {
                    $comp_size_length = $len;
                }
            }

            foreach ($archive->getFiles($filter) as $file) {
                $details = $archive->getFileData($file);
                $output->writeln(($details->isCompressed && $details->uncompressedSize > 0 ? 'x' : '-')
                                 . ' ' . str_pad(
                                     $human_readable_size ? implode($this->formatSize($details->uncompressedSize)) : $details->uncompressedSize,
                                     $uncomp_size_length,
                                     ' ',
                                     STR_PAD_LEFT)
                                 . ' ' . str_pad(
                                     $human_readable_size ? implode($this->formatSize($details->compressedSize)) : $details->compressedSize,
                                     $comp_size_length,
                                     ' ',
                                     STR_PAD_LEFT)
                                 . ' ' . $this->formatDateShort($details->modificationTime) . ' ' . $details->path);
            }
        } else {
            foreach ($archive->getFiles($filter) as $file) {
                $output->writeln($file);
            }
        }

        return 0;
    }

    protected function formatDateShort($timestamp)
    {
        static $current_year;
        if ($current_year === null) {
            $current_year = strtotime('1 january');
        }
        if ($timestamp < $current_year) {
            return strtolower(date('M d  o', $timestamp));
        } else {
            return strtolower(date('M d H:i', $timestamp));
        }
    }
}
