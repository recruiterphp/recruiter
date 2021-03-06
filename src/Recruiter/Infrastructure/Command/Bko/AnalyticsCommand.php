<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Command\Bko;

use Psr\Log\LoggerInterface;
use Recruiter\Factory;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Recruiter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class AnalyticsCommand extends Command
{
    /**
     * @var Recruiter
     */
    private $recruiter;

    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Factory $factory
     * @param LoggerInterface $logger
     */
    public function __construct(Factory $factory, LoggerInterface $logger)
    {
        parent::__construct();
        $this->factory = $factory;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setName('bko:analytics')
            ->setDescription('show recruiter analytics')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'HOSTNAME[:PORT][/DB] MongoDB coordinates',
                'mongodb://localhost:27017/recruiter'
            )
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_REQUIRED,
                'limit analytics to a specific job group'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var string */
        $target = $input->getOption('target');
        $db = $this->factory->getMongoDb(MongoURI::from($target));
        $this->recruiter = new Recruiter($db);

        $group = $input->getOption('group');
        $analytics = $this->recruiter->analytics($group);

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

            for ($i = 0; $i < count($analytic); $i++) {
                $table->setColumnStyle($i, $rightAligned);
                $table->setColumnWidth($i, $columnsWidth);
            }

            $table->render();
            echo PHP_EOL;
        }
    }

    private function calculateColumnsWidth(array $analytics): int
    {
        $maxColumns = 1;
        foreach ($analytics as $analytic) {
            $maxColumns = max($maxColumns, count($analytic));
        }

        // casual constants, found by try and error
        $terminalWidth = (new Terminal())->getWidth() - (($maxColumns + 2) * 2);

        return intval(floor($terminalWidth / $maxColumns));
    }
}
