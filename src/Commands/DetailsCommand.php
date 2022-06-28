<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use wapmorgan\UnifiedArchive\Formats;

class DetailsCommand extends BaseFileCommand
{
    protected static $defaultName = 'file:details';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Renders detailed information about file within archive')
            ->setHelp('Renders detailed information about file within archive.')
            ->addOption('detect-mimetype', null, InputOption::VALUE_NONE, 'Detect mimetype for entries by its raw content')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        list($archive, $file) = $this->getArchiveAndFile($input, $output);
        $detect_mimetype = $input->getOption('detect-mimetype');

        $details = $archive->getFileData($file);
        $output->writeln('File <info>' . $file . '</info>');
        if ($detect_mimetype) {
            $output->writeln('Mime type: <info>' . $this->getMimeTypeByStream($archive->getFileStream($file)).'</info>');
        }

        $output->writeln('Size:');
        $output->writeln("\t". 'uncompressed: '.implode(' ', $this->formatSize($details->uncompressedSize, 2)));
        $output->writeln("\t" . 'compressed: ' . implode(' ', $this->formatSize($details->compressedSize, 2)));
        $output->writeln("\t" . 'ratio: <info>' . round($details->uncompressedSize / $details->compressedSize, 6) . '/1 (' . floor($details->compressedSize / $details->uncompressedSize * 100) . '%</info>)');
        $output->writeln('Modificated: ' . $this->formatDate($details->modificationTime));
        if (!empty($comment = $details->comment))
            $output->writeln('Comment: <comment>' . $comment . '</comment>');

        if (empty($input->getArgument('file'))) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Another file? [y/N] ', false);

            if ($helper->ask($input, $output, $question)) {
                $this->execute($input, $output);
            }
        }

        return 0;
    }
}
