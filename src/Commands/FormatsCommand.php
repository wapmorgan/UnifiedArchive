<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
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
            $driver = $this->resolveDriverName($driver);
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

        $formats = Formats::getSupportedDriverFormats();
        $headers = array_keys($formats);
        array_unshift($headers, 'driver type');
        array_unshift($headers, 'driver / format');
        $table->setHeaders($headers);
        $rows = [];

        /** @var \wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver $driverClass */
        foreach (Formats::$drivers as $driverClass) {
            $row = [
                substr($driverClass, strrpos($driverClass, '\\') + 1),
                BasicDriver::$typeLabels[$driverClass::TYPE],
            ];
            foreach ($formats as $format => $formatSupportStatus) {
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
            $rows[] = $row;
        }

        $table->setRows($rows);
        //$table->setRow($i++, $row);
        $table->render();

        foreach (array_combine(array_values(self::$abilitiesShortCuts), array_keys(self::$abilitiesLabels)) as $shortCut => $label) {
            $output->writeln('<info>' . $shortCut . '</info> - ' . $label);
        }

        return 0;
    }
}
