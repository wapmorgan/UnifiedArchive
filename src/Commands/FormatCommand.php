<?php

namespace wapmorgan\UnifiedArchive\Commands;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Formats;

class FormatCommand extends BaseCommand
{
    protected static $defaultName = 'system:format';

    protected function configure()
    {
        $this
            ->setDescription('Describes possible and actual format support')
            ->setHelp('Describes possible and actual format support.')
            ->addArgument('format', InputArgument::OPTIONAL, 'format')
        ;
    }

    /**
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $formats = Formats::getDeclaredDriverFormats();
        $format = $input->getArgument('format');

        if (empty($format) || !isset($formats[$format])) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');


            $question = new ChoiceQuestion('Which format', array_keys($formats));
            $format = $helper->ask($input, $output, $question);
        }
        $output->writeln('Format <info>' . $format . '</info> drivers support');

        $table = new Table($output);
        $table->setHeaders(['format', ...array_keys(self::$abilitiesLabels)]);
        /**
         * @var int $i
         * @var \wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver $driver
         */
        foreach ($formats[$format] as $i => $driver) {
            if ($driver::isInstalled()) {
                $abilities = $driver::checkFormatSupport($format);
                $row = [$driver];

                foreach (self::$abilitiesLabels as $possibleAbility) {
                    $row[] = in_array($possibleAbility, $abilities, true) ? '+' : '';
                }

                $table->setRow($i, $row);
            } else {
                $table->setRow($i, [$driver, new TableCell('<error>not installed</error>', ['colspan' => 6])]);
            }
        }
        $table->render();

        return 0;
    }
}
