<?php

namespace wapmorgan\UnifiedArchive\Commands;

use http\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class CreateCommand extends BaseCommand
{
    protected static $defaultName = 'archive:create';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Creates new archive')
            ->setHelp('Creates new archive.')
            ->addArgument('archive', InputArgument::REQUIRED, 'New archive filename. Type of archive will be recognized from extension')
            ->addArgument('file', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Files to pack')
            ->addOption('password', NULL, InputOption::VALUE_REQUIRED, 'Password for new archive')
            ->addOption('compression', NULL, InputOption::VALUE_OPTIONAL, 'Compression level for new archive. Variants: none/weak/average/strong/maximum.', 'average')
            ->addOption('comment', NULL, InputOption::VALUE_OPTIONAL, 'Comment for new archive')
            ->addOption('path', NULL, InputOption::VALUE_OPTIONAL, 'Path resolving if destination is not passed. Variants: full/root/relative/basename', 'root')
            ->addOption('stdout', NULL, InputOption::VALUE_NONE, 'Print archive to stdout')
            ->addOption('format', NULL, InputOption::VALUE_REQUIRED, 'Format')
            ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Do not perform real archiving. Just print prepared data')
        ;
    }

    protected static $compressionLevels = [
        'none' => BasicDriver::COMPRESSION_NONE,
        'weak' => BasicDriver::COMPRESSION_WEAK,
        'average' => BasicDriver::COMPRESSION_AVERAGE,
        'strong' => BasicDriver::COMPRESSION_STRONG,
        'maximum' => BasicDriver::COMPRESSION_MAXIMUM,
    ];

    /**
     * @throws \wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException
     * @throws \wapmorgan\UnifiedArchive\Exceptions\FileAlreadyExistsException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $archive_file = $input->getArgument('archive');
        $files_to_pack = $input->getArgument('file');

        $password = $input->getOption('password');
        $compression = $input->getOption('compression');
        if (!isset(static::$compressionLevels[$compression])) {
            throw new \InvalidArgumentException('Compression "' . $compression . '" is not valid');
        }
        $compression = static::$compressionLevels[$compression];
        $path = $input->getOption('path');
        if (!in_array($path, ['full', 'root', 'relative', 'basename'], true)) {
            throw new \InvalidArgumentException('Path can not have this value');
        }
        $dry_run = $input->getOption('dry-run');
        $comment = $input->getOption('comment');
        $stdout = $input->getOption('stdout');

        if (file_exists($archive_file)) {
            if (is_dir($archive_file))
                throw new \InvalidArgumentException($archive_file . ' is a directory!');
            else {
                throw new \InvalidArgumentException('File "' . $archive_file . '" already exists!');
            }
        }

        $files_list = [];

        foreach ($files_to_pack as $i => $file_to_pack) {
            $file_to_pack = realpath($file_to_pack);
            switch ($path) {
                case 'full':
                    $destination = ltrim($file_to_pack, '/');
                    $files_list[$destination] = $file_to_pack;
                    if (!$stdout) {
                        $output->writeln(
                            '<comment>' . $file_to_pack . ' => ' . $destination . '</comment>',
                            OutputInterface::VERBOSITY_VERBOSE
                        );
                    }
                    break;
                case 'root':
                    if (is_dir($file_to_pack)) {
                        if (!$stdout) {
                            $output->writeln(
                                '<comment>' . $file_to_pack . ' => root</comment>',
                                OutputInterface::VERBOSITY_VERBOSE
                            );
                        }
                        if (!isset($files_list[''])) {
                            $files_list[''] = $file_to_pack;
                        } elseif (is_string($files_list[''])) {
                            $files_list[''] = [$files_list[''], $file_to_pack];
                        } else {
                            $files_list[''][] = $file_to_pack;
                        }
                    } else {
                        if (!$stdout) {
                            $output->writeln(
                                '<comment>' . $file_to_pack . ' => ' . basename($file_to_pack) . '</comment>',
                                OutputInterface::VERBOSITY_VERBOSE
                            );
                        }
                        $files_list[basename($file_to_pack)] = $file_to_pack;
                    }
                    break;
                case 'relative':
                    $destination = ltrim($file_to_pack, '/.');
                    $files_list[$destination] = $file_to_pack;
                    if (!$stdout) {
                        $output->writeln(
                            '<comment>' . $file_to_pack . ' => ' . $destination . '</comment>',
                            OutputInterface::VERBOSITY_VERBOSE
                        );
                    }
                    break;
                case 'basename':
                    $destination = basename($file_to_pack);
                    $files_list[$destination] = $file_to_pack;
                    if (!$stdout) {
                        $output->writeln(
                            '<comment>' . $file_to_pack . ' => ' . $destination . '</comment>',
                            OutputInterface::VERBOSITY_VERBOSE
                        );
                    }
                    break;
            }
        }

        $information = UnifiedArchive::prepareForArchiving($files_list, $archive_file);
        if ($dry_run) {
            $output->writeln('Format: <info>' . $information['type'] . '</info>');
            $output->writeln('Original size: <info>' . implode($this->formatSize($information['totalSize'])) . '</info>');
            $output->writeln('Files: <info>' . $information['numberOfFiles'] . '</info>');
            foreach ($information['files'] as $destination => $source) {
                // is folder
                if ($source === null) {
                    continue;
                }

                $output->writeln($source . ' => <comment>' . $destination . '</comment>');
            }
            return 0;
        }

        ProgressBar::setFormatDefinition('archiving', '  %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%: %message%');

        if ($stdout) {
            fwrite(STDOUT, UnifiedArchive::createInString($files_list, Formats::detectArchiveFormat($archive_file, false), $compression, $password));
            return 0;
        }
        $progressBar = new ProgressBar($output, $information['numberOfFiles']);
        $progressBar->setFormat('archiving');
        $progressBar->start();
        $archived_files = UnifiedArchive::create($files_list, $archive_file, $compression, $password, function ($currentFile, $totalFiles, $fsFilename, $archiveFilename)
        use ($progressBar) {
            if ($fsFilename === null) {
                $progressBar->setMessage('Creating ' . $archiveFilename);
            } else {
                $progressBar->setMessage($fsFilename);
            }
            $progressBar->advance();
        });
        $progressBar->finish();
        $progressBar->clear();
        $output->writeln('');

        if (!$archived_files) {
            throw new \RuntimeException('archiveFiles result is false');
        }

        $archive = $this->open($archive_file);
        if (!empty($comment)) {
            $archive->setComment($comment);
        }

        $output->writeln(
            'Created <info>' . $archive_file . '</info> with <comment>' . $archived_files . '</comment> file(s) ('
            . implode($this->formatSize($archive->getOriginalSize())) . ') of total size '
            . implode($this->formatSize(filesize($archive_file)))
        );

        return 0;
    }
}
