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
            if (strpos($driver, '\\') === false) {
                if (class_exists('\\wapmorgan\\UnifiedArchive\\Drivers\\' . $driver)) {
                    $driver = '\\wapmorgan\\UnifiedArchive\\Drivers\\' . $driver;
                } else if (class_exists('\\wapmorgan\\UnifiedArchive\\Drivers\\OneFile\\' . $driver)) {
                    $driver = '\\wapmorgan\\UnifiedArchive\\Drivers\\OneFile\\' . $driver;
                }
            }
            if ($driver[0] !== '\\') {
                $driver = '\\'.$driver;
            }
            if (!class_exists($driver) || !is_a($driver, BasicDriver::class, true)) {
                throw new \InvalidArgumentException('Class "' . $driver . '" not found or not in BasicDriver children');
            }
            $output->writeln('Supported formats by <info>' . $driver . '</info>');

            $table->setHeaders(['format', ...array_keys(self::$abilitiesLabels)]);
            foreach ($driver::getSupportedFormats() as $i => $format) {
                $abilities = $driver::checkFormatSupport($format);
                $row = [$format];

                foreach (self::$abilitiesLabels as $possibleAbility) {
                    $row[] = in_array($possibleAbility, $abilities, true) ? '+' : '';
                }

                $table->setRow($i, $row);
            }
            $table->render();
            return 0;
        }

        $headers = array_map(function ($v) { return substr($v, strrpos($v, '\\') + 1);}, Formats::$drivers);
        array_unshift($headers, 'format / driver');

        $table->setHeaders($headers);
        $i = 0;
        foreach (Formats::getSupportedDriverFormats() as $format => $formatSupportStatus) {
            $row = [$format];
            foreach (Formats::$drivers as $driverClass) {
                if (isset($formatSupportStatus[$driverClass])) {
                    $shortcuts = null;
                    foreach (self::$abilitiesShortCuts as $ability => $abilitiesShortCut) {
                        if (in_array($ability, $formatSupportStatus[$driverClass], true)) {
                            $shortcuts .= $abilitiesShortCut;
                        }
                    }
                    $row[] = $shortcuts;
                } else {
                    $row[] = '';
                }
            }

            $table->setRow($i++, $row);
        }
        $table->render();

        foreach (array_combine(array_values(self::$abilitiesShortCuts), array_keys(self::$abilitiesLabels)) as $shortCut => $label) {
            $output->writeln('<info>' . $shortCut . '</info> - ' . $label);
        }

        return 0;
    }
}
