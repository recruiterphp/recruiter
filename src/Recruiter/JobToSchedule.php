<?php

declare(strict_types=1);

namespace Recruiter;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Timeless as T;
use Timeless\Interval;
use Timeless\Moment;

/**
 * @method send() to make PHPStan happy in tests
 */
class JobToSchedule
{
    private bool $mustBeScheduled;

    public function __construct(private readonly Job $job)
    {
        $this->mustBeScheduled = false;
    }

    public function doNotRetry(): static
    {
        return $this->retryWithPolicy(new RetryPolicy\DoNotDoItAgain());
    }

    /**
     * @return $this
     */
    public function retryManyTimes($howManyTimes, Interval $timeToWaitBeforeRetry, $retriableExceptionTypes = []): static
    {
        $this->job->retryWithPolicy(
            $this->filterForRetriableExceptions(
                new RetryPolicy\RetryManyTimes($howManyTimes, $timeToWaitBeforeRetry),
                $retriableExceptionTypes,
            ),
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function retryWithPolicy(RetryPolicy $retryPolicy, $retriableExceptionTypes = []): static
    {
        $this->job->retryWithPolicy(
            $this->filterForRetriableExceptions(
                $retryPolicy,
                $retriableExceptionTypes,
            ),
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function inBackground(): static
    {
        return $this->scheduleAt(T\now());
    }

    /**
     * @return $this
     */
    public function scheduleIn(Interval $duration): static
    {
        return $this->scheduleAt($duration->fromNow());
    }

    /**
     * @return $this
     */
    public function scheduleAt(Moment $momentInTime): static
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

    /**
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
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
