<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Command\Bko;

use DateTime;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use Recruiter\Factory;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Job;
use Recruiter\Job\Repository as JobRepository;
use Recruiter\Recruiter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Timeless as T;
use Timeless\Moment;

class JobRecoverCommand extends Command
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
     * @var JobRepository
     */
    private $jobRepository;

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
            ->setName('job:recover')
            ->setDescription('re-scheduling an already archived job')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'HOSTNAME[:PORT][/DB] MongoDB coordinates',
                'mongodb://localhost:27017/recruiter'
            )
            ->addOption(
                'scheduleAt',
                's',
                InputOption::VALUE_REQUIRED,
                're-scheduling the job at specific datetime'
            )
            ->addArgument(
                'jobId',
                InputArgument::REQUIRED,
                'the id of the job in archived collection to be recovered'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var string */
        $target = $input->getOption('target');
        $db = $this->factory->getMongoDb(MongoURI::from($target));
        $this->recruiter = new Recruiter($db);
        /** @var string */
        $archivedJobId = $input->getArgument('jobId');

        $output->writeln("<info>Recovering job `$archivedJobId` ...</info>");

        $this->jobRepository = new JobRepository($db);
        $archivedJob = $this->jobRepository->archived($archivedJobId);

        $job = $this->createJobFromAnArchivedJob($archivedJob, $this->jobRepository);

        if ($input->getOption('scheduleAt')) {
            /** @var string */
            $scheduleAt = $input->getOption('scheduleAt');
            $job->scheduleAt(Moment::fromDateTime(new DateTime($scheduleAt)));
        } else {
            $job->scheduleAt(T\now());
        }

        $job
            ->scheduledBy('recovering-archived-job', $archivedJobId, -1)
            ->save();

        $output->writeln("<info>Job recovered, new job id is `</info><comment>{$job->id()}</comment><info>`</info>");
    }

    private function createJobFromAnArchivedJob(Job $archivedJob, JobRepository $repository): Job
    {
        $data = array_merge($archivedJob->export(), [
            '_id' => new ObjectId(),
            'done' => false,
            'created_at' => T\MongoDate::now(),
            'locked' => false,
            'attempts' => 0,
        ]);

        unset($data['why'], $data['last_execution']);

        return Job::import($data, $repository);
    }
}
