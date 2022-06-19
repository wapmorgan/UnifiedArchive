<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\Formats;

class FormatsCommand extends BaseCommand
{
    protected static $defaultName = 'system:formats';

    protected function configure()
    {
        $this
            ->setDescription('Lists supported archive formats with current system configuration')
            ->setHelp('Lists supported archive formats with current system configuration. You can check supported formats and supported actions with them.')
            ->addArgument('driver', InputArgument::OPTIONAL, 'Filter formats support by specific driver')
            ->addUsage('\'wapmorgan\UnifiedArchive\Drivers\SevenZip\'')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);

        /** @var string|BasicDriver $driver */
        $driver = $input->getArgument('driver');

        if ($driver !== null) {
            if ($driver[0] !== '\\') {
                $driver = '\\'.$driver;
            }
            if (!class_exists($driver) || !is_a($driver, BasicDriver::class, true)) {
                throw new \InvalidArgumentException('Class "' . $driver . '" not found or not in BasicDriver children');
            }
            $output->writeln('Supported format by <info>' . $driver . '</info>');

            $table->setHeaders(['format', 'stream', 'create', 'append', 'update', 'encrypt']);
            foreach ($driver::getSupportedFormats() as $i => $format) {
                $table->setRow($i, [
                    $format,
                    $driver::canStream($format) ? '+' : '',
                    $driver::canCreateArchive($format) ? '+' : '',
                    $driver::canAddFiles($format) ? '+' : '',
                    $driver::canDeleteFiles($format) ? '+' : '',
                    $driver::canEncrypt($format) ? '+' : '',
                ]);
            }
            $table->render();
            return 0;
        }

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
