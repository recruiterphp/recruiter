<?php

declare(strict_types=1);

namespace Recruiter\Infrastructure\Command;

use ByteUnits;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Recruiter\Concurrency\MongoLock;
use Recruiter\Factory;
use Recruiter\Geezer\Command\LeadershipEventsHandler;
use Recruiter\Geezer\Command\RobustCommand;
use Recruiter\Geezer\Command\RobustCommandRunner;
use Recruiter\Geezer\Leadership\Dictatorship;
use Recruiter\Geezer\Leadership\LeadershipStrategy;
use Recruiter\Geezer\Timing\ExponentialBackoffStrategy;
use Recruiter\Geezer\Timing\WaitStrategy;
use Recruiter\Infrastructure\Filesystem\BootstrapFile;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Infrastructure\Memory\MemoryLimitExceededException;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Recruiter;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Timeless\Interval;

class RecruiterCommand implements RobustCommand, LeadershipEventsHandler
{
    private Recruiter $recruiter;
    private Interval $consideredDeadAfter;
    private LeadershipStrategy $leadershipStrategy;
    private WaitStrategy $waitStrategy;
    private MemoryLimit $memoryLimit;

    public function __construct(private readonly Factory $factory, private readonly LoggerInterface $logger)
    {
    }

    public static function toRobustCommand(Factory $factory, LoggerInterface $logger): RobustCommandRunner
    {
        return new RobustCommandRunner(new static($factory, $logger), $logger);
    }

    /**
     * @throws MemoryLimitExceededException
     */
    public function execute(): bool
    {
        $this->rollbackLockedJobs();
        $this->scheduleRepeatableJobs();
        $assignment = $this->assignJobsToWorkers();
        $this->retireDeadWorkers();

        return count($assignment) > 0;
    }

    private function rollbackLockedJobs(): void
    {
        $rollbackStartAt = microtime(true);
        $rolledBack = $this->recruiter->rollbackLockedJobs();
        $rollbackEndAt = microtime(true);

        $logLevel = $rolledBack > 0 ? LogLevel::INFO : LogLevel::DEBUG;
        $this->log(sprintf('rolled back %d jobs in %fms', $rolledBack, ($rollbackEndAt - $rollbackStartAt) * 1000), $logLevel);
    }

    /**
     * @return array<string, string>
     *
     * @throws MemoryLimitExceededException
     */
    private function assignJobsToWorkers(): array
    {
        $pickStartAt = microtime(true);
        [$assignment, $actualNumber] = $this->recruiter->assignJobsToWorkers();
        $pickEndAt = microtime(true);
        foreach ($assignment as $worker => $job) {
            $this->log(sprintf(' tried to assign job `%s` to worker `%s`', $job, $worker), LogLevel::INFO);
        }
        $memoryUsage = ByteUnits\bytes(memory_get_usage());

        $this->log(sprintf(
            '[%s] picked jobs for %d workers in %fms, actual assignments were %d',
            $memoryUsage->format(),
            count($assignment),
            ($pickEndAt - $pickStartAt) * 1000,
            $actualNumber,
        ), LogLevel::DEBUG);

        $this->memoryLimit->ensure($memoryUsage);

        return $assignment;
    }

    private function scheduleRepeatableJobs(): void
    {
        $creationStartAt = microtime(true);
        $this->recruiter->scheduleRepeatableJobs();
        $creationEndAt = microtime(true);

        // FIXME:! log every job created?
        /* foreach ($assignment as $worker => $job) { */
        /*     $this->log(sprintf(' tried to assign job `%s` to worker `%s`', $job, $worker)); */
        /* } */

        $this->log(sprintf(
            'creation of jobs from crontab in %fms',
            ($creationEndAt - $creationStartAt) * 1000,
        ));
    }

    private function retireDeadWorkers(): void
    {
        $unlockedJobs = $this->recruiter->retireDeadWorkers(
            new \DateTimeImmutable(),
            $this->consideredDeadAfter,
        );
        $this->log(sprintf('unlocked %d jobs due to dead workers', $unlockedJobs), LogLevel::DEBUG);
    }

    public function shutdown(?\Throwable $e = null): bool
    {
        $this->recruiter->bye();
        $this->log('ok, see you space cowboy...', LogLevel::INFO);

        return true;
    }

    public function hasTerminated(): bool
    {
        return false;
    }

    public function leadershipStrategy(): LeadershipStrategy
    {
        return $this->leadershipStrategy;
    }

    public function waitStrategy(): WaitStrategy
    {
        return $this->waitStrategy;
    }

    public function name(): string
    {
        return 'start:recruiter';
    }

    public function description(): string
    {
        return 'process that look up for new jobs to assign to workers';
    }

    public function definition(): InputDefinition
    {
        $defaultMongoUri = (string) MongoURI::fromEnvironment();

        return new InputDefinition([
            new InputOption('target', 't', InputOption::VALUE_REQUIRED, 'HOSTNAME[:PORT][/DB] MongoDB coordinates', $defaultMongoUri),
            new InputOption('backoff-to', 'b', InputOption::VALUE_REQUIRED, 'Upper limit of time to wait before next polling (milliseconds)', '1600ms'),
            new InputOption('backoff-from', 'f', InputOption::VALUE_REQUIRED, 'Time to wait at least before to search for new jobs (milliseconds)', '200ms'),
            new InputOption('lease-time', 'l', InputOption::VALUE_REQUIRED, 'Maximum time to hold a lock before a refresh', '60s'),
            new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'Maximum amount of memory allocable', '256MB'),
            new InputOption('considered-dead-after', 'd', InputOption::VALUE_REQUIRED, 'Upper limit of time to wait before considering a worker dead', '30m'),
            new InputOption('log-level', null, InputOption::VALUE_REQUIRED, 'The logging level: `emergency|alert|critical|error|warning|notice|info|debug`'),
            new InputOption('bootstrap', 's', InputOption::VALUE_REQUIRED, 'A PHP file that loads the worker environment'),
        ]);
    }

    public function init(InputInterface $input): void
    {
        /** @var string */
        $mongoTarget = $input->getOption('target');
        $db = $this->factory->getMongoDb(MongoURI::from($mongoTarget));
        $lock = MongoLock::forProgram('RECRUITER', $db->selectCollection('metadata'));

        $this->leadershipStrategy = new Dictatorship($lock, Interval::parse($input->getOption('lease-time'))->seconds());

        $this->waitStrategy = new ExponentialBackoffStrategy(
            Interval::parse($input->getOption('backoff-from'))->ms(),
            Interval::parse($input->getOption('backoff-to'))->ms(),
        );

        $this->consideredDeadAfter = Interval::parse($input->getOption('considered-dead-after'));

        $this->memoryLimit = new MemoryLimit($input->getOption('memory-limit'));

        $this->recruiter = new Recruiter($db);
        $this->recruiter->createCollectionsAndIndexes();

        if ($input->getOption('bootstrap')) {
            /** @var string $bootstrap */
            $bootstrap = $input->getOption('bootstrap');
            BootstrapFile::fromFilePath($bootstrap)->load($this->recruiter);
        }
    }

    private function log(string $message, string $level = LogLevel::DEBUG): void
    {
        $this->logger->log(
            $level,
            $message,
            [
                'hostname' => gethostname(),
                'program' => $this->name(),
                'datetime' => date('c'),
                'pid' => posix_getpid(),
            ],
        );
    }

    public function leadershipAcquired(): void
    {
        if (is_callable('recruiter_became_master')) {
            recruiter_became_master($this->recruiter);
        }
    }

    public function leadershipLost(): void
    {
        if (is_callable('recruiter_stept_back')) {
            recruiter_stept_back($this->recruiter);
        }
    }
}
