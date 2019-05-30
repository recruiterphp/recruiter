<?php

namespace Recruiter;

use MongoDB\BSON\ObjectId;
use Recruiter\Scheduler\Repository;
use Recruiter\Job\Repository as JobsRepository;
use Recruiter\RetryPolicy;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Timeless as T;
use Timeless\Interval;
use Timeless\Moment;

class Scheduler
{
    private $job;

    private $schedulers;

    private $status;

    private $schedulePolicy;

    public static function around(Repeatable $repeatable, Repository $repository, Recruiter $recruiter)
    {
        $retryPolicy = ($repeatable instanceof Retriable) ?
            $workable->retryWithPolicy() :
            new RetryPolicy\DoNotDoItAgain()
        ;

        return new self(
            self::initialize(),
            $repeatable,
            null,
            $retryPolicy,
            $repository,
            $recruiter
        );
    }

    public static function import($document, Repository $repository)
    {
        return new self(
            $document,
            WorkableInJob::import($document),
            SchedulePolicyInJob::import($document),
            RetryPolicyInJob::import($document),
            $repository
        );
    }

    public function __construct(
        array $status,
        Workable $workable,
        ?SchedulePolicy $schedulePolicy,
        ?RetryPolicy $retryPolicy,
        Repository $schedulers
    ) {
        $this->status = $status;
        $this->workable = $workable;
        $this->schedulePolicy = $schedulePolicy;
        $this->retryPolicy = $retryPolicy;
        $this->schedulers = $schedulers;
    }

    public function create()
    {
        $this->schedulers->create($this);

        return $this;
    }

    public function repeatWithPolicy(SchedulePolicy $schedulePolicy)
    {
        $this->schedulePolicy = $schedulePolicy;

        return $this;
    }

    private static function initialize()
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

    public function export()
    {
        return array_merge(
            $this->status,
            WorkableInJob::export($this->workable, 'FIXME:!'),
            SchedulePolicyInJob::export($this->schedulePolicy),
            RetryPolicyInJob::export($this->retryPolicy)
        );
    }

    private function wasAlreadyScheduled($nextScheduling)
    {
        if (!$this->status['last_scheduling']['scheduled_at']) {
            return false;
        }

        $lastScheduling = T\MongoDate::toMoment($this->status['last_scheduling']['scheduled_at']);

        return $lastScheduling == $nextScheduling;
    }

    private function aJobIsStillRunning(JobsRepository $jobs)
    {
        if (!$this->status['last_scheduling']['job_id']) {
            return false;
        }

        try {
            $alreadyScheduledJob = $jobs->scheduled($this->status['last_scheduling']['job_id']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function schedule(JobsRepository $jobs)
    {
        $nextScheduling = $this->schedulePolicy->next();
        if ($this->wasAlreadyScheduled($nextScheduling)) {
            return;
        }

        if ($this->status['unique'] && $this->aJobIsStillRunning($jobs)) {
            return;
        }

        $this->status['last_scheduling']['scheduled_at'] = T\MongoDate::from($nextScheduling);
        $this->status['last_scheduling']['job_id'] = null;
        $this->status['attempts'] = $this->status['attempts'] + 1;
        $this->schedulers->save($this);

        $jobToSchedule = (new JobToSchedule(Job::around($this->workable, $jobs)))
            ->scheduleAt($nextScheduling)
            ->retryWithPolicy($this->retryPolicy)
            ->scheduledBy('scheduler', $this->status['urn'], $this->status['attempts'])
            ->execute()
        ;

        $this->status['last_scheduling']['job_id'] = $jobToSchedule;
        $this->schedulers->save($this);
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy, $retriableExceptionTypes = [])
    {
        $this->retryPolicy = $this->filterForRetriableExceptions(
            $retryPolicy,
            $retriableExceptionTypes
        );

        return $this;
    }

    public function withUrn(string $urn)
    {
        $this->status['urn'] = $urn;

        return $this;
    }

    public function unique(bool $unique)
    {
        $this->status['unique'] = $unique;

        return $this;
    }

    public function urn()
    {
        return $this->status['urn'];
    }

    private function filterForRetriableExceptions(RetryPolicy $retryPolicy, $retriableExceptionTypes = [])
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
