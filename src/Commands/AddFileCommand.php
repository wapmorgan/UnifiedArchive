<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddFileCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'file:add';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Packs new file in archive')
            ->setHelp('Packs new file in archive. If used path = relative, then file will have relative name in archive with trimmed dots')
            ->addArgument('source', InputArgument::REQUIRED, 'Source file on disk or "-" for standard input')
            ->addArgument('destination', InputArgument::OPTIONAL, 'Destination filename in archive')
            ->addOption('path', NULL, InputOption::VALUE_OPTIONAL, 'Path resolving if destination is not passed. Variants: full, relative, basename', 'relative')
            ->addUsage('archive.zip - < LICENSE')
            ->addUsage('archive.zip LICENSE LICENSE')
            ->addUsage('archive.zip ../Morphos/.travis.yml --path=full')
            ->addUsage('archive.zip ../Morphos/.travis.yml --path=relative')
            ->addUsage('archive.zip ../Morphos/.travis.yml --path=basename')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getArgument('source');
        $destination = $input->getArgument('destination');
        $path = $input->getOption('path');
        if (!in_array($path, ['full', 'relative', 'basename'], true)) {
            throw new \InvalidArgumentException('Path can not have this value');
        }

        if ($source === '-') {
            stream_set_blocking(STDIN, false);
            $data = stream_get_contents(STDIN);
            stream_set_blocking(STDIN, true);
            $data_size = strlen($data);
            if ($data_size === 0) {
                throw new \LogicException('Empty input!');
            }
            if (empty($destination)) {
                throw new \LogicException('Source and destination can not be empty');
            }
            $output->writeln('<info>Read ' . $data_size . ' from input</info>');
        } else {
            $data_size = filesize($source);
        }

        if (empty($destination)) {
            switch ($path) {
                case 'full':
                    $destination = ltrim(realpath($source), '/');
                    break;
                case 'relative':
                    $destination = ltrim($source, '/.');
                    break;
                case 'basename':
                    $destination = basename($source);
                    break;
            }
        }

        $archive = $this->getArchive($input, $output);
        if ($source === '-') {
            $added_files = $archive->addFileFromString($destination, $data) ? 1 : 0;
        } else {
            $added_files = $archive->add([$destination => $source]);
        }
        if ($added_files === 1) {
            $details = $archive->getFileData($destination);
            $output->writeln('Added <comment>' . $source . '</comment>('
                             . implode($this->formatSize($data_size)) . ') as '
                             . $destination . ' ('
                             . implode($this->formatSize($details->compressedSize))
                             . ')');
        }

        return 0;
    }
}
