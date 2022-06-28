<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommentCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'archive:comment';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Sets comment on archive')
            ->setHelp('Sets comment on archive.')
            ->addArgument('comment', InputArgument::REQUIRED, 'Comment')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $archive = $this->getArchive($input, $output);
        $comment = $input->getArgument('comment');
        if (empty($comment)) {
            $comment = null;
        }

        if (!empty($previous_comment = $archive->getComment())) {
            $output->writeln('Comment "' . $previous_comment . '" replaced', OutputInterface::OUTPUT_RAW);
        } else if ($comment === null) {
            $output->writeln('Comment deleted', OutputInterface::OUTPUT_RAW);
        } else {
            $output->writeln('Comment set', OutputInterface::OUTPUT_RAW);
        }

        $archive->setComment($comment);

        return 0;
    }
}
