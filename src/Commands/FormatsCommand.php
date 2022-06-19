<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Formats;

class FormatsCommand extends BaseCommand
{
    protected static $defaultName = 'system:formats';

    protected function configure()
    {
        $this
            ->setDescription('Lists supported archive formats with current system configuration')
            ->setHelp('Lists supported archive formats with current system configuration. You can check supported formats and supported actions with them.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['format', 'open', 'stream', 'create', 'append', 'update', 'encrypt', 'drivers']);

        $i = 0;
        foreach (Formats::getFormatsReport() as $format => $config) {
            $table->setRow($i++, [
                $format,
                $config['open'] ? '+' : '',
                $config['stream'] ? '+' : '',
                $config['create'] ? '+' : '',
                $config['append'] ? '+' : '',
                $config['update'] ? '+' : '',
                $config['encrypt'] ? '+' : '',
                new TableCell(implode("\n", $config['drivers']), ['rowspan' => count($config['drivers'])])
            ]);
        }
        $table->render();
    }
}
