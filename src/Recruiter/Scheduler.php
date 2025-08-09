<?php

namespace Recruiter;

use Recruiter\Job\Repository as JobsRepository;
use Recruiter\Scheduler\Repository;
use Timeless as T;

class Scheduler
{
    public static function around(Repeatable $repeatable, Repository $repository): self
    {
        $retryPolicy = ($repeatable instanceof Retriable) ?
            $repeatable->retryWithPolicy() :
            new RetryPolicy\DoNotDoItAgain();

        return new self(
            self::initialize(),
            $repeatable,
            null,
            $retryPolicy,
            $repository,
        );
    }

    /**
     * @param array<string, mixed> $document
     *
     * @throws \Exception
     */
    public static function import(array $document, Repository $repository): self
    {
        return new self(
            $document,
            RepeatableInJob::import($document['job']),
            SchedulePolicyInJob::import($document),
            RetryPolicyInJob::import($document['job']),
            $repository,
        );
    }

    /**
     * @param array<string, mixed> $status
     */
    public function __construct(private array $status, private readonly Repeatable $repeatable, private ?SchedulePolicy $schedulePolicy, private ?RetryPolicy $retryPolicy, private readonly Repository $schedulers)
    {
    }

    /**
     * @return $this
     */
    public function create(): self
    {
        $this->schedulers->create($this);

        return $this;
    }

    public function repeatWithPolicy(SchedulePolicy $schedulePolicy): self
    {
        $this->schedulePolicy = $schedulePolicy;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private static function initialize(): array
    {
        return [
            'urn' => null,
            'created_at' => T\MongoDate::now(),
            'last_scheduling' => [
                'scheduled_at' => null,
                'job_id' => null,
            ],
            'attempts' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return array_merge(
            $this->status,
            SchedulePolicyInJob::export($this->schedulePolicy),
            [
                'job' => array_merge(
                    WorkableInJob::export($this->repeatable, 'execute'),
                    RetryPolicyInJob::export($this->retryPolicy),
                ),
            ],
        );
    }

    private function wasAlreadyScheduled(T\Moment $nextScheduling): bool
    {
        if (!$this->status['last_scheduling']['scheduled_at']) {
            return false;
        }

        $lastScheduling = T\MongoDate::toMoment($this->status['last_scheduling']['scheduled_at']);

        return $lastScheduling == $nextScheduling;
    }

    private function aJobIsStillRunning(JobsRepository $jobs): bool
    {
        if (!$this->status['last_scheduling']['job_id']) {
            return false;
        }

        try {
            $alreadyScheduledJob = $jobs->scheduled($this->status['last_scheduling']['job_id']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function schedule(JobsRepository $jobs): void
    {
        if (!$this->schedulePolicy) {
            throw new \RuntimeException('You need to assign a `SchedulePolicy` (use `repeatWithPolicy` to inject it) in order to schedule a job');
        }

        $nextScheduling = $this->schedulePolicy->next();
        if ($this->wasAlreadyScheduled($nextScheduling)) {
            return;
        }

        if ($this->status['unique'] && $this->aJobIsStillRunning($jobs)) {
            return;
        }

        $this->status['last_scheduling']['scheduled_at'] = T\MongoDate::from($nextScheduling);
        $this->status['last_scheduling']['job_id'] = null;
        ++$this->status['attempts'];
        $this->schedulers->save($this);

        $jobToSchedule = new JobToSchedule(Job::around($this->repeatable, $jobs))
            ->scheduleAt($nextScheduling)
            ->retryWithPolicy($this->retryPolicy)
            ->scheduledBy('scheduler', $this->status['urn'], $this->status['attempts'])
            ->execute()
        ;

        $this->status['last_scheduling']['job_id'] = $jobToSchedule;
        $this->schedulers->save($this);
    }

    /**
     * @param class-string[] $retriableExceptionTypes
     *
     * @return $this
     */
    public function retryWithPolicy(RetryPolicy $retryPolicy, array $retriableExceptionTypes = []): self
    {
        $this->retryPolicy = $this->filterForRetriableExceptions(
            $retryPolicy,
            $retriableExceptionTypes,
        );

        return $this;
    }

    public function withUrn(string $urn): self
    {
        $this->status['urn'] = $urn;

        return $this;
    }

    public function unique(bool $unique): self
    {
        $this->status['unique'] = $unique;

        return $this;
    }

    public function urn(): string
    {
        return $this->status['urn'];
    }

    public function schedulePolicy(): ?SchedulePolicy
    {
        return $this->schedulePolicy;
    }

    /**
     * @param class-string|class-string[] $retriableExceptionTypes
     */
    private function filterForRetriableExceptions(RetryPolicy $retryPolicy, string|array $retriableExceptionTypes = []): RetryPolicy
    {
        if (!is_array($retriableExceptionTypes)) {
            $retriableExceptionTypes = [$retriableExceptionTypes];
        }
        if (!empty($retriableExceptionTypes)) {
            $retryPolicy = new RetryPolicy\RetriableExceptionFilter($retryPolicy, $retriableExceptionTypes);
        }

        return $retryPolicy;
    }
}
