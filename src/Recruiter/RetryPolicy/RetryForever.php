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
    private readonly Interval $timeToWaitBeforeRetry;

    public function __construct(int|Interval $timeToWaitBeforeRetry)
    {
        if (!($timeToWaitBeforeRetry instanceof Interval)) {
            $timeToWaitBeforeRetry = T\seconds($timeToWaitBeforeRetry);
        }
        $this->timeToWaitBeforeRetry = $timeToWaitBeforeRetry;
    }

    public static function afterSeconds(int|Interval $timeToWaitBeforeRetry = 60): self
    {
        return new self($timeToWaitBeforeRetry);
    }

    public function schedule(JobAfterFailure $job): void
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
