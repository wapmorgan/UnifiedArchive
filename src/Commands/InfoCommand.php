<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;

class InfoCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'archive:info';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Renders archive info')
            ->setHelp('Renders archive info.')
        ;
    }

    /**
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $file = realpath($input->getArgument('archive'));
        $archive = $this->getArchive($input, $output);

        $output->writeln('Filename: ' . $file . ' (changed <comment>' . $this->formatDate(filemtime($file)) . '</comment>)');
        $output->writeln('Type: <info>' . $archive->getFormat() . '</info>, mime <info>' . $archive->getMimeType() . '</info> (via driver <comment>' . $archive->getDriverType() . '</comment>)');
        $output->writeln('Contains: ' . $archive->countFiles() . ' file' . ($archive->countFiles() > 1 ? 's' : null));
        $output->writeln('Size:');
        $output->writeln("\t". 'uncompressed: '.implode(' ', $this->formatSize($archive->getOriginalSize(), 2)));
        $output->writeln("\t" . 'compressed: ' . implode(' ', $this->formatSize($archive->getCompressedSize(), 2)));
        $output->writeln("\t" . 'ratio: <info>' . round($archive->getOriginalSize() / $archive->getCompressedSize(), 6) . '/1 (' . floor($archive->getCompressedSize() / $archive->getOriginalSize() * 100) . '%</info>)');
        if ($archive->getDriver()->checkAbility(BasicDriver::GET_COMMENT) && !empty($comment = $archive->getComment()))
            $output->writeln('Comment: <comment>' . $comment . '</comment>');

        return 0;
    }
}
