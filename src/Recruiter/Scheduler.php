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
        $this->schedulers->save($this);

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
            '_id' => new ObjectId(),
            'urn' => null,
            'created_at' => T\MongoDate::now(),
            'last_scheduling_at' => null,
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

    private function wasAlreadyScheduled()
    {
        if (!$this->status['last_scheduling_at']) {
            return false;
        }

        $lastScheduling = T\MongoDate::toMoment($this->status['last_scheduling_at']);
        $nextScheduling = $this->schedulePolicy->next();

        return $lastScheduling == $nextScheduling;
    }

    public function schedule(JobsRepository $jobs)
    {
        if ($this->wasAlreadyScheduled()) {
            return;
        }

        $nextScheduling = $this->schedulePolicy->next();
        $this->status['last_scheduling_at'] = T\MongoDate::from($nextScheduling);
        $this->status['attempts'] = $this->status['attempts'] + 1;
        $this->schedulers->save($this);

        $jobToSchedule = (new JobToSchedule(Job::around($this->workable, $jobs)))
            ->scheduleAt($nextScheduling)
            ->retryWithPolicy($this->retryPolicy)
            ->scheduledBy('scheduler', $this->status['_id'], $this->status['attempts'])
            ->execute()
        ;
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

    public function id()
    {
        return $this->status['_id'];
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
