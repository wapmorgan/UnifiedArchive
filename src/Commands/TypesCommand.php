<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TypesCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'files:types';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Accumulates info archive entries\' extensions')
            ->setHelp('Accumulates info about archive entries\' extensions')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $archive = $this->getArchive($input, $output);

        $extensions = [];

        foreach ($archive->getFiles() as $file) {
            $details = $archive->getFileData($file);

            $extension = pathinfo($file, PATHINFO_EXTENSION);

            if (!isset($extensions[$extension])) {
                $extensions[$extension] = [
                    $extension,
                    0, // number of files
                    0, // total uncompressed size
                    0, // total compressed size
                    0, // total ratio
                    0, // latest modification datetime
                ];
            }

            $extensions[$extension][1]++;
            $extensions[$extension][2] += $details->uncompressedSize;
            $extensions[$extension][3] += $details->compressedSize;
            if ($details->modificationTime > $extensions[$extension][5]) {
                $extensions[$extension][5] = $details->modificationTime;
            }
        }

        foreach ($extensions as $extensionI => $extension) {
            if ($extensions[$extensionI][2] > 0) {
                $extensions[$extensionI][4] = round($extensions[$extensionI][3] / $extensions[$extensionI][2], 1);
            } else {
                $extensions[$extensionI][4] = '-';
            }
            $extensions[$extensionI][5] = $this->formatDate($extensions[$extensionI][5]);
        }

        $table = new Table($output);
        $table->setHeaders(['Extension', 'Total files', 'Total uncompressed size', 'Total compressed size', 'Total ratio', 'Latest modification datetime']);
        $table->setRows($extensions);
        $table->render();

        return 0;
    }
}
