<?php

declare(strict_types=1);

namespace Recruiter\Infrastructure\Command\Bko;

use Psr\Log\LoggerInterface;
use Recruiter\Factory;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Scheduler\Repository as SchedulerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RemoveSchedulerCommand extends Command
{
    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SchedulerRepository
     */
    private $schedulerRepository;

    public function __construct(Factory $factory, LoggerInterface $logger)
    {
        parent::__construct();
        $this->factory = $factory;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setName('scheduler:remove')
            ->setDescription('list all schedulers')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'HOSTNAME[:PORT][/DB] MongoDB coordinates',
                MongoURI::fromEnvironment(),
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        /** @var string */
        $target = $input->getOption('target');
        $db = $this->factory->getMongoDb(MongoURI::from($target));
        $this->schedulerRepository = new SchedulerRepository($db);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputData = $this->buildOutputData();
        if (!$outputData) {
            $output->writeln('There are no schedulers yet.');

            return self::SUCCESS;
        }

        $this->printTable($outputData, $output);

        $urns = array_column($outputData, 'urn');
        $selectedUrn = $this->selectUrnToDelete($urns, $input, $output);

        if ($selectedUrn) {
            $this->schedulerRepository->deleteByUrn($selectedUrn);
            $this->logger->info("[Recruiter] the scheduler with urn `$selectedUrn` was deleted!");
        }

        return self::SUCCESS;
    }

    private function selectUrnToDelete(array $urns, InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Please select the scheduler which you want delete',
            $urns,
            null,
        );
        $question->setErrorMessage('scheduler %s is invalid.');

        $selectedUrn = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion("\nYou have just selected: <info>" . $selectedUrn . "</info>\nConfirm the deletion? [y,N] ", false);
        if (!$helper->ask($input, $output, $question)) {
            return false;
        }

        return $selectedUrn;
    }

    private function printTable(array $data, OutputInterface $output)
    {
        $rows = [];
        foreach ($data as $row) {
            $rows[] = array_values($row);
            $rows[] = new TableSeparator();
        }

        $table = new Table($output);
        $table
            ->setHeaders(array_keys($data[0]))
            ->setRows($rows)
        ;

        $table->render();
        echo PHP_EOL;
    }

    protected function buildOutputData()
    {
        $outputData = [];
        $i = 0;

        $schedulers = $this->schedulerRepository->all();
        if (!$schedulers) {
            return null;
        }

        foreach ($schedulers as $scheduler) {
            $data = $scheduler->export();

            $info = [
                'createdAt' => $data['created_at']->toDateTime()->format('c'),
                'lastScheduling' => $data['last_scheduling']['scheduled_at']->toDateTime()->format('c'),
                'workable' => $data['job']['workable']['class'],
                'policy' => $scheduler->schedulePolicy()->export(),
            ];

            $stringifyValue = function ($value) {
                if (is_array($value)) {
                    return var_export($value, true);
                } else {
                    return "`$value`";
                }
            };

            $infoString = '';
            foreach ($info as $k => $v) {
                $v = $stringifyValue($v);
                $infoString .= "<comment>$k</comment> => $v\n";
            }

            $outputData[] = [
                '' => '<info>' . $i++ . '</info>',
                'urn' => $data['urn'],
                'info' => $infoString,
            ];
        }

        return $outputData;
    }
}
