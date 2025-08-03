<?php

declare(strict_types=1);

namespace Recruiter\Infrastructure\Command\Bko;

use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use Recruiter\Factory;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Job;
use Recruiter\Job\Repository as JobRepository;
use Recruiter\Recruiter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Timeless as T;
use Timeless\Moment;

class JobRecoverCommand extends Command
{
    private Recruiter $recruiter;
    private JobRepository $jobRepository;

    public function __construct(private readonly Factory $factory, private readonly LoggerInterface $logger)
    {
        parent::__construct();
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
                MongoURI::fromEnvironment(),
            )
            ->addOption(
                'scheduleAt',
                's',
                InputOption::VALUE_REQUIRED,
                're-scheduling the job at specific datetime',
            )
            ->addArgument(
                'jobId',
                InputArgument::REQUIRED,
                'the id of the job in archived collection to be recovered',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
            $job->scheduleAt(Moment::fromDateTime(new \DateTime($scheduleAt)));
        } else {
            $job->scheduleAt(T\now());
        }

        $job
            ->scheduledBy('recovering-archived-job', $archivedJobId, -1)
            ->save()
        ;

        $output->writeln("<info>Job recovered, new job id is `</info><comment>{$job->id()}</comment><info>`</info>");

        return self::SUCCESS;
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
