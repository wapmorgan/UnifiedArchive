<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\Formats;

class DriversCommand extends BaseCommand
{
    protected static $defaultName = 'system:drivers';

    protected function configure()
    {
        $this
            ->setDescription('Lists supported drivers for different formats and their installation status in system')
            ->setHelp('Lists supported drivers for different formats and their installation status in system. You need to manually install any of them.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $notInstalled = [];
        $drivers = [];
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        /** @var BasicDriver $driverClass */
        foreach (Formats::$drivers as $driverClass) {
            $description = $driverClass::getDescription();
            if (!$driverClass::isInstalled()) {
                $drivers[$driverClass::TYPE][$driverClass] = [$description, false, $driverClass::getInstallationInstruction()];
//                $notInstalled[] = [$driverClass, $description, $install];
            } else {
                $drivers[$driverClass::TYPE][$driverClass] = [$description, true];
            }

//            if (!empty($install)) {
//            } else {
//                $output->writeln($formatter->formatSection($driverClass, $description) . ': ' . implode(', ', $driverClass::getSupportedFormats()));
//            }
        }

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $type_match_rules = [
            BasicDriver::TYPE_EXTENSION => 'php extension',
            BasicDriver::TYPE_UTILITIES => 'utilities + php bridge',
            BasicDriver::TYPE_PURE_PHP => 'pure php',
        ];

        foreach ($drivers as $type => $typeDrivers) {
            foreach ($typeDrivers as $typeDriverClass => $typeDriverConfig) {
                $type_messages = [];
                if ($typeDriverConfig[1]) {
                    $type_messages[] = '<info>' . $typeDriverClass . '</info>: ' . $typeDriverConfig[0];
                } else {
                    $type_messages[] = '<error>' . $typeDriverClass . '</error>: ' . $typeDriverConfig[0];
                    $type_messages[] = $this->formatInstallation($typeDriverConfig[2], 4);
                }
                $output->writeln($formatter->formatSection(
                    $type_match_rules[$type],
                    implode("\n", $type_messages), $typeDriverConfig[1] ? 'info' : 'error'));
            }
        }

//        if (!empty($notInstalled)) {
//            foreach ($notInstalled as $data) {
//                $output->writeln($formatter->formatSection($data[0], $data[1] . ': ' . implode(', ', $data[0]::getSupportedFormats()), 'error'));
//                $data[2] = preg_replace('~`(.+?)`~', '<options=bold,underscore>$1</>', $data[2]);
//                $output->writeln($formatter->formatSection($data[0], $data[2], 'comment'));
//            }
//        }

        return 0;
    }

    protected function formatInstallation($doc, $leftPadding = 4)
    {
        return implode("\n", array_map(
            function($line) use ($leftPadding) { return str_repeat(' ', $leftPadding) . $line; },
            explode(
                "\n",
                preg_replace('~`(.+?)`~', '<options=bold,underscore>$1</>', $doc)
            )
        ));
    }
}
