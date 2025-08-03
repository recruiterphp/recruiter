<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Timeless as T;
use Timeless\Interval;

final class RetryForever implements RetryPolicy
{
    use RetryPolicyBehaviour;
    private $timeToWaitBeforeRetry;

    public function __construct($timeToWaitBeforeRetry)
    {
        if (!($timeToWaitBeforeRetry instanceof Interval)) {
            $timeToWaitBeforeRetry = T\seconds($timeToWaitBeforeRetry);
        }
        $this->timeToWaitBeforeRetry = $timeToWaitBeforeRetry;
    }

    public static function afterSeconds($timeToWaitBeforeRetry = 60)
    {
        return new static($timeToWaitBeforeRetry);
    }

    public function schedule(JobAfterFailure $job)
    {
        $job->scheduleIn($this->timeToWaitBeforeRetry);
    }

    public function export(): array
    {
        return [
            'seconds_to_wait_before_retry' => $this->timeToWaitBeforeRetry->seconds(),
        ];
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new self(
            T\seconds($parameters['seconds_to_wait_before_retry']),
        );
    }

    public function isLastRetry(Job $job): bool
    {
        return false;
    }
}
