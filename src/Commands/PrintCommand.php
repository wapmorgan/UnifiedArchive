<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class PrintCommand extends BaseFileCommand
{
    protected static $defaultName = 'file:print';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Renders RAW archive file content')
            ->setHelp('Renders RAW archive file content.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var $archive UnifiedArchive */
        list($archive, $file) = $this->getArchiveAndFile($input, $output);
        fpassthru($archive->getFileStream($file));

        return 0;
    }
}
