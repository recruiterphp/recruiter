<?php

namespace Recruiter;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Timeless as T;
use Timeless\Interval;
use Timeless\Moment;

class JobToSchedule
{
    /** @var bool */
    private $mustBeScheduled;

    public function __construct(private readonly Job $job)
    {
        $this->mustBeScheduled = false;
    }

    public function doNotRetry()
    {
        return $this->retryWithPolicy(new RetryPolicy\DoNotDoItAgain());
    }

    public function retryManyTimes($howManyTimes, Interval $timeToWaitBeforeRetry, $retriableExceptionTypes = [])
    {
        $this->job->retryWithPolicy(
            $this->filterForRetriableExceptions(
                new RetryPolicy\RetryManyTimes($howManyTimes, $timeToWaitBeforeRetry),
                $retriableExceptionTypes,
            ),
        );

        return $this;
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy, $retriableExceptionTypes = [])
    {
        $this->job->retryWithPolicy(
            $this->filterForRetriableExceptions(
                $retryPolicy,
                $retriableExceptionTypes,
            ),
        );

        return $this;
    }

    public function inBackground()
    {
        return $this->scheduleAt(T\now());
    }

    public function scheduleIn(Interval $duration)
    {
        return $this->scheduleAt($duration->fromNow());
    }

    public function scheduleAt(Moment $momentInTime)
    {
        $this->mustBeScheduled = true;
        $this->job->scheduleAt($momentInTime);

        return $this;
    }

    /**
     * @return $this
     */
    public function inGroup(array|string|null $group): static
    {
        if (!empty($group)) {
            $this->job->inGroup($group);
        }

        return $this;
    }

    public function taggedAs(array|string $tags): static
    {
        if (!empty($tags)) {
            $this->job->taggedAs(is_array($tags) ? $tags : [$tags]);
        }

        return $this;
    }

    public function withUrn(string $urn): static
    {
        $this->job->withUrn($urn);

        return $this;
    }

    public function scheduledBy(string $namespace, string $id, int $nth): static
    {
        $this->job->scheduledBy($namespace, $id, $nth);

        return $this;
    }

    public function execute(): string
    {
        if ($this->mustBeScheduled) {
            $this->job->save();
        } else {
            $this->job->execute($this->emptyEventDispatcher());
        }

        return (string) $this->job->id();
    }

    private function emptyEventDispatcher(): EventDispatcher
    {
        return new EventDispatcher();
    }

    public function __call($name, $arguments)
    {
        $this->job->methodToCallOnWorkable($name);

        return $this->execute();
    }

    public function export(): array
    {
        return $this->job->export();
    }

    public static function import($document, $repository): self
    {
        return new self(Job::import($document, $repository));
    }

    private function filterForRetriableExceptions($retryPolicy, $retriableExceptionTypes)
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
