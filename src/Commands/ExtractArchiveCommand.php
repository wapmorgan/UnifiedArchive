<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractArchiveCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'archive:extract';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Extracts all archive contents and overwrites existing files')
            ->setHelp('Extracts all archive contents and overwrites existing files.')
            ->addArgument('outputDir', InputArgument::REQUIRED, 'Folder to extract contents')
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

        if (disk_free_space($output_dir) < $archive->getOriginalSize()) {
            throw new \LogicException('Archive size ' . implode($this->formatSize($archive->getOriginalSize())) . ' is more that available on disk '
                                      . implode($this->formatSize(disk_free_space($output_dir))));
        }
        $archive->extract($output_dir, $entry_selector, true);
        $output->writeln('<info>Extracted all archive contents (' . implode($this->formatSize($archive->getOriginalSize())) . ')</info>');

        return 0;
    }
}
