<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\FormatterHelper;
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
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        /** @var BasicDriver $driverClass */
        foreach (Formats::$drivers as $driverClass) {
            $description = $driverClass::getDescription();
            $install = $driverClass::getInstallationInstruction();
            if (!empty($install)) {
                $notInstalled[] = [$driverClass, $description, $install];
            } else {
                $output->writeln($formatter->formatSection($driverClass, $description) . ': ' . implode(', ', $driverClass::getSupportedFormats()));
            }
        }

        if (!empty($notInstalled)) {
            foreach ($notInstalled as $data) {
                $output->writeln($formatter->formatSection($data[0], $data[1] . ': ' . implode(', ', $data[0]::getSupportedFormats()), 'error'));
                $data[2] = preg_replace('~`(.+?)`~', '<options=bold,underscore>$1</>', $data[2]);
                $output->writeln($formatter->formatSection($data[0], $data[2], 'comment'));
            }
        }

        return 0;
    }
}
