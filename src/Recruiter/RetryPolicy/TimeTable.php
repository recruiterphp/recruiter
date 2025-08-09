<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Timeless as T;

class TimeTable implements RetryPolicy
{
    use RetryPolicyBehaviour;

    public readonly int $howManyRetries;

    /**
     * @throws \Exception
     */
    public function __construct(private ?array $timeTable)
    {
        if (is_null($timeTable)) {
            $timeTable = [
                '5 minutes ago' => '1 minute',
                '1 hour ago' => '5 minutes',
                '24 hours ago' => '1 hour',
            ];
        }
        $this->timeTable = $timeTable;
        $this->howManyRetries = self::estimateHowManyRetriesIn($timeTable);
    }

    public function schedule(JobAfterFailure $job): void
    {
        foreach ($this->timeTable as $timeSpent => $rescheduleIn) {
            if ($this->hasBeenCreatedLessThan($job, $timeSpent)) {
                $this->rescheduleIn($job, $rescheduleIn);
                break;
            }
        }
    }

    public function isLastRetry(Job $job): bool
    {
        $timeSpents = array_keys($this->timeTable);
        $timeSpent = end($timeSpents);

        return !$this->hasBeenCreatedLessThan($job, $timeSpent);
    }

    public function export(): array
    {
        return ['time_table' => $this->timeTable];
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new self($parameters['time_table']);
    }

    private function hasBeenCreatedLessThan($job, $relativeTime)
    {
        return $job->createdAt()->isAfter(
            T\Moment::fromTimestamp(strtotime((string) $relativeTime, T\now()->seconds())),
        );
    }

    private function rescheduleIn($job, $relativeTime): void
    {
        $job->scheduleAt(
            T\Moment::fromTimestamp(strtotime((string) $relativeTime, T\now()->seconds())),
        );
    }

    private static function estimateHowManyRetriesIn(array $timeTable): int
    {
        $now = T\now()->seconds();
        $howManyRetries = 0;
        $timeWindowInSeconds = 0;
        foreach ($timeTable as $timeWindow => $rescheduleTime) {
            $timeWindowInSeconds = ($now - strtotime((string) $timeWindow, $now)) - $timeWindowInSeconds;
            if ($timeWindowInSeconds <= 0) {
                throw new \Exception("Time window `$timeWindow` is invalid, must be in the past");
            }
            $rescheduleTimeInSeconds = (strtotime((string) $rescheduleTime, $now) - $now);
            if ($rescheduleTimeInSeconds <= 0) {
                throw new \Exception("Reschedule time `$rescheduleTime` is invalid, must be in the future");
            }
            if ($rescheduleTimeInSeconds > $timeWindowInSeconds) {
                throw new \Exception("Reschedule time `$rescheduleTime` is invalid, must be greater than the time window");
            }
            $howManyRetries += floor($timeWindowInSeconds / $rescheduleTimeInSeconds);
        }

        return $howManyRetries;
    }
}
