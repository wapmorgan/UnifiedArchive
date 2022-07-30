<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractFilesCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'files:extract';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Extracts archive contents by selector')
            ->setHelp('Extracts all archive contents by selector.')
            ->addArgument('outputDir', InputArgument::REQUIRED, 'Folder to extract contents')
            ->addArgument('entrySelector', InputArgument::OPTIONAL, 'Prefix of entry selector. Default is null, means root of archive')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output_dir = $input->getArgument('outputDir');
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        } else if (!is_writable($output_dir)) {
            chmod($output_dir, 0777);
        }
        $archive = $this->getArchive($input, $output);
        $entry_selector = $input->getArgument('entrySelector');

        $archive->extract($output_dir, $entry_selector, true);
        $output->writeln('<info>Extracted:</info> ' . implode(', ', $entry_selector) . ' (' . count($entry_selector) . ') file(s)');

        return 0;
    }
}
