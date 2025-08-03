<?php

namespace Recruiter\Acceptance;

use MongoDB\Collection;
use MongoDB\Database;
use PHPUnit\Framework\TestCase;
use Recruiter\Concurrency\Timeout;
use Recruiter\Factory;
use Recruiter\Infrastructure\Persistence\Mongodb\URI;
use Recruiter\Recruiter;
use Recruiter\RetryPolicy;
use Recruiter\Workable\ShellCommand;
use Timeless as T;

abstract class BaseAcceptanceTestCase extends TestCase
{
    protected Database $recruiterDb;
    protected Recruiter $recruiter;
    protected Collection $scheduled;
    protected Collection $archived;
    protected Collection $roster;
    protected Collection $schedulers;
    protected array $files;
    protected int $jobs;
    private ?array $processRecruiter;
    private ?array $processCleaner;
    private array $processWorkers;
    private array $lastStatus;

    protected function setUp(): void
    {
        $factory = new Factory();
        $this->recruiterDb = $factory->getMongoDb(URI::fromEnvironment(), []);
        $this->cleanDb();
        $this->files = ['/tmp/recruiter.log', '/tmp/worker.log'];
        $this->cleanLogs();
        $this->roster = $this->recruiterDb->selectCollection('roster');
        $this->scheduled = $this->recruiterDb->selectCollection('scheduled');
        $this->archived = $this->recruiterDb->selectCollection('archived');
        $this->schedulers = $this->recruiterDb->selectCollection('schedulers');
        $this->recruiter = new Recruiter($this->recruiterDb);
        $this->jobs = 0;
        $this->processRecruiter = null;
        $this->processCleaner = null;
        $this->processWorkers = [];
    }

    protected function tearDown(): void
    {
        $this->terminateProcesses(SIGKILL);
    }

    protected function cleanDb(): void
    {
        $this->recruiterDb->drop();
    }

    protected function clean(): void
    {
        $this->terminateProcesses(SIGKILL);
        $this->cleanLogs();
        $this->cleanDb();
        $this->jobs = 0;
    }

    public function cleanLogs(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    protected function numberOfWorkers(): int
    {
        return $this->roster->countDocuments();
    }

    protected function waitForNumberOfWorkersToBe($expectedNumber, $howManySeconds = 1): void
    {
        Timeout::inSeconds($howManySeconds, "workers to be $expectedNumber")
            ->until(function () use ($expectedNumber) {
                $this->recruiter->retireDeadWorkers(new \DateTimeImmutable(), T\seconds(0));

                return $this->numberOfWorkers() == $expectedNumber;
            })
        ;
    }

    protected function startRecruiter(): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = __DIR__ . '/../../../';
        /* $process = proc_open('exec php bin/recruiter --backoff-to=5s --lease-time 10s --considered-dead-after 20s >> /tmp/recruiter.log 2>&1', $descriptors, $pipes, $cwd); */

        $process = proc_open('exec php bin/recruiter start:recruiter --backoff-to 5000ms --lease-time 10s --considered-dead-after 20s >> /tmp/recruiter.log 2>&1', $descriptors, $pipes, $cwd);

        Timeout::inSeconds(1, 'recruiter to be up')
            ->until(function () use ($process) {
                $status = proc_get_status($process);

                return $status['running'];
            })
        ;

        $this->processRecruiter = [$process, $pipes, 'recruiter'];

        return $this->processRecruiter;
    }

    protected function startCleaner(): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = __DIR__ . '/../../../';
        $process = proc_open('exec php bin/recruiter start:cleaner --wait-at-least=5s --wait-at-most=1m --lease-time 20s >> /tmp/cleaner.log 2>&1', $descriptors, $pipes, $cwd);
        Timeout::inSeconds(1, 'cleaner to be up')
            ->until(function () use ($process) {
                $status = proc_get_status($process);

                return $status['running'];
            })
        ;
        $this->processCleaner = [$process, $pipes, 'cleaner'];

        return $this->processCleaner;
    }

    protected function startWorker(array $additionalOptions = [])
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $options = $this->optionsToString(array_merge([
            'bootstrap' => 'examples/bootstrap.php',
            'backoff-from' => '100ms',
            'backoff-to' => '15000ms',
        ], $additionalOptions));

        $cwd = __DIR__ . '/../../../';
        $process = proc_open("exec php bin/recruiter start:worker $options >> /tmp/worker.log 2>&1", $descriptors, $pipes, $cwd);

        Timeout::inSeconds(1, 'worker to be up')
            ->until(function () use ($process) {
                $status = proc_get_status($process);

                return $status['running'];
            })
        ;
        // proc_get_status($process);

        $this->processWorkers[] = [$process, $pipes, 'worker'];

        return end($this->processWorkers);
    }

    protected function stopProcessWithSignal(array $processAndPipes, $signal): void
    {
        [$process, $pipes, $name] = $processAndPipes;
        proc_terminate($process, $signal);
        $this->lastStatus = proc_get_status($process);
        Timeout::inSeconds(30, fn () => 'termination of process: ' . var_export($this->lastStatus, true) . " after sending the `$signal` signal to it")
            ->until(function () use ($process) {
                $this->lastStatus = proc_get_status($process);

                return false == $this->lastStatus['running'];
            })
        ;
    }

    /**
     * @param int $duration milliseconds
     */
    protected function enqueueJob($duration = 10, $tag = 'generic'): void
    {
        $workable = ShellCommand::fromCommandLine('sleep ' . ($duration / 1000));
        $workable
            ->asJobOf($this->recruiter)
            ->inGroup($tag)
            ->inBackground()
            ->execute()
        ;
        ++$this->jobs;
    }

    protected function enqueueJobWithRetryPolicy(int $duration, RetryPolicy $retryPolicy): void
    {
        $workable = ShellCommand::fromCommandLine('sleep ' . ($duration / 1000));
        $workable
            ->asJobOf($this->recruiter)
            ->retryWithPolicy($retryPolicy)
            ->inBackground()
            ->execute()
        ;
        ++$this->jobs;
    }

    protected function start(int $workers): void
    {
        $this->startRecruiter();
        $this->startCleaner();
        for ($i = 0; $i < $workers; ++$i) {
            $this->startWorker();
        }
    }

    private function terminateProcesses(int $signal): void
    {
        if ($this->processRecruiter) {
            $this->stopProcessWithSignal($this->processRecruiter, $signal);
            $this->processRecruiter = null;
        }
        if ($this->processCleaner) {
            $this->stopProcessWithSignal($this->processCleaner, $signal);
            $this->processCleaner = null;
        }
        foreach ($this->processWorkers as $processWorker) {
            $this->stopProcessWithSignal($processWorker, $signal);
        }
        $this->processWorkers = [];
    }

    protected function restartWorkerGracefully($workerIndex): void
    {
        $this->stopProcessWithSignal($this->processWorkers[$workerIndex], SIGTERM);
        $this->processWorkers[$workerIndex] = $this->startWorker();
    }

    protected function restartWorkerByKilling($workerIndex): void
    {
        $this->stopProcessWithSignal($this->processWorkers[$workerIndex], SIGKILL);
        $this->processWorkers[$workerIndex] = $this->startWorker();
    }

    protected function restartRecruiterGracefully(): void
    {
        $this->stopProcessWithSignal($this->processRecruiter, SIGTERM);
        $this->startRecruiter();
    }

    protected function restartRecruiterByKilling(): void
    {
        $this->stopProcessWithSignal($this->processRecruiter, SIGKILL);
        $this->startRecruiter();
    }

    protected function files(): string
    {
        $logs = '';
        if (getenv('TEST_DUMP')) {
            foreach ($this->files as $file) {
                $logs .= $file . ':' . PHP_EOL;
                $logs .= file_get_contents($file);
            }
        } else {
            $logs .= var_export($this->files, true);
        }

        return $logs;
    }

    private function optionsToString(array $options = []): string
    {
        $optionsString = '';

        foreach ($options as $option => $value) {
            $optionsString .= " --$option=$value";
        }

        return $optionsString;
    }
}
