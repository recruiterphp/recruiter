<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Timeless as T;
use Timeless\Interval;

class RetryManyTimes implements RetryPolicy
{
    use RetryPolicyBehaviour;

    private Interval $timeToWaitBeforeRetry;

    public function __construct(private readonly int $retryHowManyTimes, int|Interval $timeToWaitBeforeRetry)
    {
        if (!($timeToWaitBeforeRetry instanceof Interval)) {
            $timeToWaitBeforeRetry = T\seconds($timeToWaitBeforeRetry);
        }
        $this->timeToWaitBeforeRetry = $timeToWaitBeforeRetry;
    }

    public static function forTimes($retryHowManyTimes, int|Interval $timeToWaitBeforeRetry = 60): static
    {
        return new static($retryHowManyTimes, $timeToWaitBeforeRetry);
    }

    public function schedule(JobAfterFailure $job): void
    {
        if ($job->numberOfAttempts() <= $this->retryHowManyTimes) {
            $job->scheduleIn($this->timeToWaitBeforeRetry);
        } else {
            $job->archive('tried-too-many-times');
        }
    }

    public function export(): array
    {
        return [
            'retry_how_many_times' => $this->retryHowManyTimes,
            'seconds_to_wait_before_retry' => $this->timeToWaitBeforeRetry->seconds(),
        ];
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new self(
            $parameters['retry_how_many_times'],
            T\seconds($parameters['seconds_to_wait_before_retry']),
        );
    }

    public function isLastRetry(Job $job): bool
    {
        return $job->numberOfAttempts() > $this->retryHowManyTimes;
    }
}
