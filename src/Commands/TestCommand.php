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
        $test_result = $archive->test($input->getArgument('filter'));
        if ($test_result === true) {
            $output->writeln('<info>Archive is ok!</info>');
        } else {
            $output->writeln('<error>Failed:</error>:');
            array_walk($test_result, static function ($file) use ($output) {
                $output->writeln('- ' . $file);
            });
        }
        return 0;
    }
}
