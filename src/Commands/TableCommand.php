<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TableCommand extends BaseArchiveCommand
{
    protected static $defaultName = 'files:table';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Lists archive entries as table')
            ->setHelp('Lists archive entries as table.')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Files filter (as for fnmatch). If no * passed in filter, it will be added at the end of filter')
            ->addOption('human-readable-size', null, InputOption::VALUE_NONE, 'Use human-readable size')
            ->addOption('detect-mimetype', null, InputOption::VALUE_NONE, 'Detect mimetype for entries by its raw content')
            ->addOption('sort', 's', InputOption::VALUE_REQUIRED, 'Sort files in table by one of fields: filename/size/xsize/ratio/date/mimetype/crc/comment. By default is stored')
            ->addOption('sort-desc', null, InputOption::VALUE_NONE, 'Set sort order to desc. By default it is asc')
        ;
    }

    /**
     * @throws \wapmorgan\UnifiedArchive\Exceptions\NonExistentArchiveFileException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $archive = $this->getArchive($input, $output);
        $filter = $input->getArgument('filter');
        $human_readable_size = $input->getOption('human-readable-size');
        $detect_mimetype = $input->getOption('detect-mimetype');
        $sort = $input->getOption('sort');
        $sort_desc = $input->getOption('sort-desc');

        $headers = [
            'Filename',
            'Size',
            'xSize',
            'Ratio',
            'Date',
//            'Crc',
            'Comment'
        ];
        if ($detect_mimetype) {
            array_splice($headers, 4, 0, ['Mimetype']);
        }

        $sort_field = array_search($sort, array_map('strtolower', $headers));

        if (!empty($filter) && strpos($filter, '*') === false) {
            $filter .= '*';
        }

        $table = new Table($output);
        $table->setHeaders($headers);

        $rows = [];

        foreach ($archive->getFiles($filter) as $i => $file) {
            $details = $archive->getFileData($file);

            if ($human_readable_size) {
                $un_comp_size = implode($this->formatSize($details->uncompressedSize));
                $comp_size = implode($this->formatSize($details->compressedSize));
            } else {
                $un_comp_size = $details->uncompressedSize;
                $comp_size = $details->compressedSize;
            }

            $row = [
                $details->path,
                $un_comp_size,
                $comp_size,
                $details->uncompressedSize > 0
                    ? round($details->compressedSize / $details->uncompressedSize, 1)
                    : '-',
                $this->formatDate($details->modificationTime),
//                $details->crc32,
                $details->comment,
            ];
            if ($detect_mimetype) {
                // @todo May be a bug in future. Need to review
                array_splice($row, 4, 0, [
                    $this->getMimeTypeByStream($archive->getFileStream($file))
                ]);
            }
            $rows[] = $row;
//            $table->setRow($i, $row);

//            $len = strlen($un_comp_size);
//            if ($len > $uncomp_size_length) {
//                $uncomp_size_length = $len;
//            }
//            $len = strlen($comp_size);
//            if ($len > $comp_size_length) {
//                $comp_size_length = $len;
//            }
        }

        if ($sort !== null) {
            usort($rows, function (array $a, array $b) use ($sort_field) {
                if ($a[$sort_field] > $b[$sort_field]) {
                    return 1;
                }
                if ($a[$sort_field] < $b[$sort_field]) {
                    return -1;
                }
                return 0;
            });
            if ($sort_desc) {
                $rows = array_reverse($rows);
            }
        }

        $table->setRows($rows);

//        $table->setColumnWidth(0, 0);
//        $table->setColumnWidth(1, $uncomp_size_length);
//        $table->setColumnWidth(2, $comp_size_length);
//        $table->setColumnWidth(3, 18);
        $table->render();

        return 0;
    }
}
