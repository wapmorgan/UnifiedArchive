<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FoldersCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'files:folders';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Accumulates folders list and their size')
            ->setHelp('Accumulates folders list and their size.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $archive = $this->getArchive($input, $output);

        $folders = [];
        foreach ($archive->getFiles() as $file) {
            $file_folder = dirname($file);
            if (empty($file_folder)) {
                $file_folder = '/';
            }

            if (!isset($folders[$file_folder])) {
                $folders[$file_folder] = [
                    0, // total files number
                    0, // total uncompressed size
                    0, // total compressed size
                ];
            }

            $details = $archive->getFileData($file);
            $folders[$file_folder][0]++;
            $folders[$file_folder][1] = $details->uncompressedSize;
            $folders[$file_folder][2] = $details->compressedSize;
        }

        ksort($folders);

        // iterate again and add sub-dirs stats to parent dirs
        foreach ($folders as $folder => $folderStat) {
            $parent_folder = $folder;
            do {
                $parent_folder = dirname($parent_folder);
                if (in_array($parent_folder, ['.', '/'], true)) {
                    $parent_folder = null;
                }

                if (isset($folders[$parent_folder])) {
                    $folders[$parent_folder][0] += $folderStat[0];
                    $folders[$parent_folder][1] += $folderStat[1];
                    $folders[$parent_folder][2] += $folderStat[2];
                }

            } while (!empty($parent_folder));
        }

        $table = new Table($output);
        $table->setHeaders(['Folder', 'Files', 'Size', 'xSize']);
        $i = 0;
        foreach ($folders as $folder => $folderStat) {
            $table->setRow($i++, [
                $folder,
                $folderStat[0],
                implode($this->formatSize($folderStat[1])),
                implode($this->formatSize($folderStat[2])),
            ]);
        }
        $table->setStyle('compact');
        $table->render();

        return 0;
    }
}
