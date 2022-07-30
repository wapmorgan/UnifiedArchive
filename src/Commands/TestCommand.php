<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'files:test';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Tests archive contents')
            ->setHelp('Tests archive contents.')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Files filter (as for fnmatch). If no * passed in filter, it will be added at the end of filter')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $archive = $this->getArchive($input, $output);
        $files = $archive->getFiles($input->getArgument('filter'));

        $errored = [];
        foreach ($files as $file) {
            $output->write($file . ' ... ');
            if ($archive->test($file) === true) {
                $output->writeln('<info>ok</info>');
            } else {
                $errored[] = $file;
                $output->writeln('<error>error</error>');
            }
        }

        if (!empty($errored)) {
            return 1;
        }

        return 0;
    }
}
