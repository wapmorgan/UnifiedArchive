<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class DeleteFileCommand extends BaseFileCommand
{
    protected static $defaultName = 'file:delete';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Deletes one file from archive')
            ->setHelp('Deletes one file from archive')
        ;
    }

    /**
     * @throws \wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException
     * @throws \wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var UnifiedArchive $archive
         * @var string $file
         */
        list($archive, $file) = $this->getArchiveAndFile($input, $output);

        $archive->delete($file);
        $output->writeln('<comment>- file "' . $file . '"</comment>');

        return 0;
    }
}
