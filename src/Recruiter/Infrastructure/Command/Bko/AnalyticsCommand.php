<?php

declare(strict_types=1);

namespace Recruiter\Infrastructure\Command\Bko;

use Recruiter\Factory;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Recruiter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class AnalyticsCommand extends Command
{
    public function __construct(private readonly Factory $factory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('bko:analytics')
            ->setDescription('show recruiter analytics')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'HOSTNAME[:PORT][/DB] MongoDB coordinates',
                (string) MongoURI::fromEnvironment(),
            )
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_REQUIRED,
                'limit analytics to a specific job group',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string */
        $target = $input->getOption('target');
        $db = $this->factory->getMongoDb(MongoURI::from($target));
        $recruiter = new Recruiter($db);

        $group = $input->getOption('group');
        $analytics = $recruiter->analytics($group);

        $rightAligned = new TableStyle();
        $rightAligned->setPadType(STR_PAD_LEFT);
        $columnsWidth = $this->calculateColumnsWidth($analytics);

        foreach ($analytics as $section => $analytic) {
            $table = new Table($output);
            $table
                ->setHeaderTitle(strtoupper($section))
                ->setHeaders(array_keys($analytic))
                ->setRows([array_values($analytic)])
            ;

            for ($i = 0; $i < count($analytic); ++$i) {
                $table->setColumnStyle($i, $rightAligned);
                $table->setColumnWidth($i, $columnsWidth);
            }

            $table->render();
            echo PHP_EOL;
        }

        return self::SUCCESS;
    }

    private function calculateColumnsWidth(array $analytics): int
    {
        $maxColumns = 1;
        foreach ($analytics as $analytic) {
            $maxColumns = max($maxColumns, count($analytic));
        }

        // casual constants, found by trial and error
        $terminalWidth = new Terminal()->getWidth() - (($maxColumns + 2) * 2);

        return intval(floor($terminalWidth / $maxColumns));
    }
}
