<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractFileCommand extends BaseFileCommand
{
    protected static $defaultName = 'file:extract';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Extracts a file from archive to disk')
            ->setHelp('Extracts a file from archive to disk.')
            ->addArgument('destination', InputArgument::OPTIONAL, 'File destination (if set folder - extracts file to folder)')
            ->addOption('overwrite', null, InputOption::VALUE_OPTIONAL, 'Overwrite file or not, if exists with the same name', false)
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        list($archive, $file) = $this->getArchiveAndFile($input, $output);
        $destination = $input->getArgument('destination');
        if (is_dir($destination)) {
            $destination = $destination . '/' . basename($file);
        }

        $overwrite = $input->getOption('overwrite');

        if (file_exists($destination) && !$overwrite) {
            if ($input->getOption('no-interaction')) {
                throw new \LogicException('File destination ' . $destination . ' exists');
            }
        }

        return 0;
    }
}
