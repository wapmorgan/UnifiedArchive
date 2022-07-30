<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class BaseFileCommand extends BaseArchiveCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Archive entry')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     * @throws \Exception
     */
    protected function getArchiveAndFile(InputInterface $input, OutputInterface $output)
    {
        $archive = $this->getArchive($input, $output);
        $file = $input->getArgument('file');
        $files = $archive->getFiles();

        if (empty($file)) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');

            $question = new ChoiceQuestion('Which file', $files);
            $file = $helper->ask($input, $output, $question);
        } else if (!in_array($file, $files, true)) {
            throw new \InvalidArgumentException('File "' . $file . '" not found in archive');
        }
        $output->writeln('<comment>Selecting file ' . $file . '</comment>', OutputInterface::VERBOSITY_VERY_VERBOSE);

        return [$archive, $file];
    }
}
