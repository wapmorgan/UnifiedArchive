<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Formats;

class BaseArchiveCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->addArgument('archive', InputArgument::REQUIRED, 'Archive file')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for archive')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return \wapmorgan\UnifiedArchive\UnifiedArchive
     * @throws \Exception
     */
    protected function getArchive(InputInterface $input, OutputInterface $output)
    {
        $file = realpath($input->getArgument('archive'));
        $output->writeln('<comment>Opening ' . $file . '</comment>', OutputInterface::VERBOSITY_VERY_VERBOSE);
        if (!is_file($file)) {
            throw new \InvalidArgumentException('File ' . $input->getArgument('archive') . ' is not accessible');
        }
        $output->writeln('<comment>Format ' . Formats::detectArchiveFormat($file).'</comment>', OutputInterface::VERBOSITY_VERY_VERBOSE);
        $password = $input->getOption('password');
        if (empty($password)) {
            $password = null;
        } else {
            $output->writeln('<comment>Passing password: ' . strlen($password).'</comment>', OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
        $archive = $this->open($file, $password);
        $output->writeln('<comment>Driver ' . $archive->getDriverType() . '</comment>', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln('<comment>Driver abilities: ' . implode(', ', $this->getDriverFormatAbilities($archive->getDriverType(), $archive->getFormat())) . '</comment>', OutputInterface::VERBOSITY_VERBOSE);
        return $archive;
    }

    /**
     * @param BasicDriver $driver
     * @param $format
     * @return array
     */
    protected function getDriverFormatAbilities($driver, $format)
    {
        $abilities = $driver::checkFormatSupport($format);
        return array_keys(array_intersect(self::$abilitiesLabels, $abilities));
    }
}
