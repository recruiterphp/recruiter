<?php

namespace Recruiter;

use Timeless\Moment;
use Timeless\Interval;
use Timeless\MongoDate;

class JobAfterFailure
{
    /** @var Job */
    private $job;

    /** @var JobExecution */
    private $lastJobExecution;

    /** @var bool */
    private $hasBeenScheduled;

    /** @var bool */
    private $hasBeenArchived;

    public function __construct(Job $job, JobExecution $lastJobExecution)
    {
        $this->job = $job;
        $this->lastJobExecution = $lastJobExecution;
        $this->hasBeenScheduled = false;
        $this->hasBeenArchived = false;
    }

    public function createdAt()
    {
        return $this->job->createdAt();
    }

    public function inGroup($group)
    {
        $this->job->inGroup($group);
        $this->job->save();
    }

    public function scheduleIn(Interval $in)
    {
        $this->scheduleAt($in->fromNow());
    }

    public function scheduleAt(Moment $at)
    {
        $this->hasBeenScheduled = true;
        $this->job->scheduleAt($at);
        $this->job->save();
    }

    public function archive($why)
    {
        $this->hasBeenArchived = true;
        $this->job->archive($why);
    }

    public function causeOfFailure()
    {
        return $this->lastJobExecution->causeOfFailure();
    }

    public function lastExecutionDuration()
    {
        return $this->lastJobExecution->duration();
    }

    public function numberOfAttempts()
    {
        return $this->job->numberOfAttempts();
    }

    public function archiveIfNotScheduled()
    {
        if (!$this->hasBeenScheduled && !$this->hasBeenArchived) {
            $this->archive('not-scheduled-by-retry-policy');
            return true;
        }
        return false;
    }

    public function hasBeenArchived()
    {
        return $this->hasBeenArchived;
    }
}
