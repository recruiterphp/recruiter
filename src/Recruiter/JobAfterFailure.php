<?php

declare(strict_types=1);

namespace Recruiter;

use Timeless\Interval;
use Timeless\Moment;

class JobAfterFailure
{
    private bool $hasBeenScheduled;

    private bool $hasBeenArchived;

    public function __construct(private readonly Job $job, private readonly JobExecution $lastJobExecution)
    {
        $this->hasBeenScheduled = false;
        $this->hasBeenArchived = false;
    }

    public function createdAt(): Moment
    {
        return $this->job->createdAt();
    }

    /**
     * @param string|string[] $group
     */
    public function inGroup(array|string $group): void
    {
        $this->job->inGroup($group);
        $this->job->save();
    }

    public function scheduleIn(Interval $in): void
    {
        $this->scheduleAt($in->fromNow());
    }

    public function scheduleAt(Moment $at): void
    {
        $this->hasBeenScheduled = true;
        $this->job->scheduleAt($at);
        $this->job->save();
    }

    public function archive(string $why): void
    {
        $this->hasBeenArchived = true;
        $this->job->archive($why);
    }

    public function causeOfFailure(): ?\Throwable
    {
        return $this->lastJobExecution->causeOfFailure();
    }

    public function lastExecutionDuration(): Interval
    {
        return $this->lastJobExecution->duration();
    }

    public function numberOfAttempts(): int
    {
        return $this->job->numberOfAttempts();
    }

    public function archiveIfNotScheduled(): bool
    {
        if (!$this->hasBeenScheduled && !$this->hasBeenArchived) {
            $this->archive('not-scheduled-by-retry-policy');

            return true;
        }

        return false;
    }

    public function hasBeenArchived(): bool
    {
        return $this->hasBeenArchived;
    }
}
