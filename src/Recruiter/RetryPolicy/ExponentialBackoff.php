<?php

declare(strict_types=1);

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Timeless as T;
use Timeless\Interval;

class ExponentialBackoff implements RetryPolicy
{
    use RetryPolicyBehaviour;

    private Interval $timeToInitiallyWaitBeforeRetry;

    public static function forTimes($retryHowManyTimes, $timeToInitiallyWaitBeforeRetry = 60): static
    {
        return new static($retryHowManyTimes, $timeToInitiallyWaitBeforeRetry);
    }

    public function atFirstWaiting($timeToInitiallyWaitBeforeRetry): static
    {
        return new static($this->retryHowManyTimes, $timeToInitiallyWaitBeforeRetry);
    }

    /**
     * @params integer $interval  in seconds
     * @params integer $timeToWaitBeforeRetry  in seconds
     */
    public static function forAnInterval($interval, $timeToInitiallyWaitBeforeRetry): static
    {
        if (!($timeToInitiallyWaitBeforeRetry instanceof Interval)) {
            $timeToInitiallyWaitBeforeRetry = T\seconds($timeToInitiallyWaitBeforeRetry);
        }
        $numberOfRetries = round(
            log($interval / $timeToInitiallyWaitBeforeRetry->seconds())
            / log(2),
        );

        return new static($numberOfRetries, $timeToInitiallyWaitBeforeRetry);
    }

    public function __construct(private $retryHowManyTimes, int|Interval $timeToInitiallyWaitBeforeRetry)
    {
        if (!($timeToInitiallyWaitBeforeRetry instanceof Interval)) {
            $timeToInitiallyWaitBeforeRetry = T\seconds($timeToInitiallyWaitBeforeRetry);
        }
        $this->timeToInitiallyWaitBeforeRetry = $timeToInitiallyWaitBeforeRetry;
    }

    public function schedule(JobAfterFailure $job): void
    {
        if ($job->numberOfAttempts() <= $this->retryHowManyTimes) {
            $retryInterval = T\seconds(2 ** ($job->numberOfAttempts() - 1) * $this->timeToInitiallyWaitBeforeRetry->seconds());
            $job->scheduleIn($retryInterval);
        } else {
            $job->archive('tried-too-many-times');
        }
    }

    public function export(): array
    {
        return [
            'retry_how_many_times' => $this->retryHowManyTimes,
            'seconds_to_initially_wait_before_retry' => $this->timeToInitiallyWaitBeforeRetry->seconds(),
        ];
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new self(
            $parameters['retry_how_many_times'],
            T\seconds($parameters['seconds_to_initially_wait_before_retry']),
        );
    }

    public function isLastRetry(Job $job): bool
    {
        return $job->numberOfAttempts() > $this->retryHowManyTimes;
    }
}
