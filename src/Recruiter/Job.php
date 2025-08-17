<?php

declare(strict_types=1);

namespace Recruiter;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection as MongoCollection;
use MongoDB\Driver\Exception\BulkWriteException;
use Recruiter\Exception\ImportException;
use Recruiter\Job\Event;
use Recruiter\Job\EventListener;
use Recruiter\Job\Repository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Timeless as T;
use Timeless\Moment;

class Job
{
    public static function around(Workable $workable, Repository $repository): self
    {
        return new self(
            self::initialize(),
            $workable,
            ($workable instanceof Retriable) ?
                $workable->retryWithPolicy() : new RetryPolicy\DoNotDoItAgain(),
            new JobExecution(),
            $repository,
        );
    }

    /**
     * @param array{
     *     _id: ObjectId,
     *     done: bool,
     *     created_at: UTCDateTime,
     *     scheduled_at?: UTCDateTime,
     *     locked: bool,
     *     attempts: int,
     *     group: string,
     *     tags?: string[],
     *     workable: array{
     *         method: string,
     *         class?: class-string,
     *         parameters?: array<mixed>,
     *     },
     *     last_execution?: array{
     *         started_at: UTCDateTime,
     *         ended_at: UTCDateTime,
     *         crashed: bool,
     *         duration: int,
     *         result: mixed,
     *         class?: class-string,
     *         message?: string,
     *         trace?: string,
     *     },
     *     scheduled?: array{
     *         by: array{
     *             namespace: string,
     *             urn: string,
     *         },
     *         executions: int,
     *     },
     * } $document
     *
     * @throws ImportException
     */
    public static function import(array $document, Repository $repository): self
    {
        return new self(
            $document,
            WorkableInJob::import($document),
            RetryPolicyInJob::import($document),
            JobExecution::import($document),
            $repository,
        );
    }

    /**
     * @param array{
     *     _id: ObjectId,
     *     done: bool,
     *     created_at: UTCDateTime,
     *     scheduled_at?: UTCDateTime,
     *     locked: bool,
     *     attempts: int,
     *     group: string,
     *     tags?: string[],
     *     workable: array{
     *         method: string,
     *         class?: class-string,
     *         parameters?: array<mixed>,
     *     },
     *     last_execution?: array{
     *         started_at: UTCDateTime,
     *         ended_at: UTCDateTime,
     *         crashed: bool,
     *         duration: int,
     *         result: mixed,
     *         class?: class-string,
     *         message?: string,
     *         trace?: string,
     *     },
     * } $status
     */
    public function __construct(
        private array $status,
        private readonly Workable $workable,
        private RetryPolicy $retryPolicy,
        private JobExecution $lastJobExecution,
        private readonly Repository $repository,
    ) {
    }

    public function id(): ObjectId
    {
        return $this->status['_id'];
    }

    public function createdAt(): Moment
    {
        return T\MongoDate::toMoment($this->status['created_at']);
    }

    public function numberOfAttempts(): int
    {
        return $this->status['attempts'];
    }

    /**
     * @return $this
     */
    public function retryWithPolicy(RetryPolicy $retryPolicy): static
    {
        $this->retryPolicy = $retryPolicy;

        return $this;
    }

    /**
     * @param string[] $tags
     *
     * @return $this
     */
    public function taggedAs(array $tags): static
    {
        if (!empty($tags)) {
            $this->status['tags'] = $tags;
        }

        return $this;
    }

    /**
     * @param string[]|string $group
     *
     * @return $this
     */
    public function inGroup(array|string $group): static
    {
        if (is_array($group)) {
            throw new \RuntimeException('Group can be only single string, for other uses use `taggedAs` method.
                Received group: `' . var_export($group, true) . '`');
        }

        if (!empty($group)) {
            $this->status['group'] = $group;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function scheduleAt(Moment $at): static
    {
        $this->status['locked'] = false;
        $this->status['scheduled_at'] = T\MongoDate::from($at);

        return $this;
    }

    /**
     * @return $this
     */
    public function withUrn(string $urn): static
    {
        $this->status['urn'] = $urn;

        return $this;
    }

    /**
     * @return $this
     */
    public function scheduledBy(string $namespace, string $id, int $executions): static
    {
        $this->status['scheduled'] = [
            'by' => [
                'namespace' => $namespace,
                'urn' => $id,
            ],
            'executions' => $executions,
        ];

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function methodToCallOnWorkable(string $method): void
    {
        if (!method_exists($this->workable, $method)) {
            throw new \Exception("Unknown method '$method' on workable instance");
        }
        $this->status['workable']['method'] = $method;
    }

    public function execute(EventDispatcherInterface $eventDispatcher): JobExecution
    {
        $methodToCall = $this->status['workable']['method'];
        try {
            if ($this->recoverFromCrash($eventDispatcher)) {
                $this->beforeExecution($eventDispatcher);
                $result = $this->workable->$methodToCall($this->retryStatistics());
                $this->afterExecution($result, $eventDispatcher);
            }
        } catch (\Throwable $exception) {
            $this->afterFailure($exception, $eventDispatcher);
        }

        return $this->lastJobExecution;
    }

    /**
     * @return array{
     *     job_id: string,
     *     retry_number: int,
     *     is_last_retry: bool,
     *     last_execution: ?array{
     *         started_at: UTCDateTime,
     *         ended_at: UTCDateTime,
     *         crashed: bool,
     *         duration: int,
     *         result: mixed,
     *         class?: class-string,
     *         message?: string,
     *         trace?: string,
     *     },
     * }
     */
    public function retryStatistics(): array
    {
        return [
            'job_id' => (string) $this->id(),
            'retry_number' => $this->status['attempts'],
            'is_last_retry' => $this->retryPolicy->isLastRetry($this),
            'last_execution' => array_key_exists('last_execution', $this->status)
                ? $this->status['last_execution']
                : null,
        ];
    }

    public function save(): void
    {
        $this->repository->save($this);
    }

    public function archive(string $why): void
    {
        $this->status['why'] = $why;
        $this->status['locked'] = false;
        unset($this->status['scheduled_at']);
        $this->repository->archive($this);
    }

    /**
     * @return array{
     *     _id: ObjectId,
     *     done: bool,
     *     created_at: UTCDateTime,
     *     scheduled_at?: UTCDateTime,
     *     locked: bool,
     *     attempts: int,
     *     group: string,
     *     tags?: string[],
     *     workable: array{
     *         method: string,
     *         class?: class-string,
     *         parameters?: array<mixed>,
     *     },
     *     last_execution?: array{
     *         started_at: UTCDateTime,
     *         ended_at: UTCDateTime,
     *         crashed: bool,
     *         duration: int,
     *         result: mixed,
     *         class?: class-string,
     *         message?: string,
     *         trace?: string,
     *     },
     *     scheduled?: array{
     *         by: array{
     *             namespace: string,
     *             urn: string,
     *         },
     *         executions: int,
     *     },
     *     retry_policy?: array<string, mixed>,
     *     why?: string
     * }
     */
    public function export(): array
    {
        return array_merge(
            $this->status,
            $this->lastJobExecution->export(),
            $this->tagsToUseFor($this->workable),
            WorkableInJob::export($this->workable, $this->status['workable']['method']),
            RetryPolicyInJob::export($this->retryPolicy),
        );
    }

    /**
     * @return $this
     */
    public function beforeExecution(EventDispatcherInterface $eventDispatcher): static
    {
        ++$this->status['attempts'];
        $this->lastJobExecution = new JobExecution();
        $this->lastJobExecution->started($this->scheduledAt());
        $this->emit('job.started', $eventDispatcher);
        if ($this->hasBeenScheduled()) {
            $this->save();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function afterExecution(mixed $result, EventDispatcherInterface $eventDispatcher): static
    {
        $this->status['done'] = true;
        $this->lastJobExecution->completedWith($result);
        $this->emit('job.ended', $eventDispatcher);
        $this->triggerOnWorkable('afterSuccess');
        if ($this->hasBeenScheduled()) {
            $this->archive('done');
        }

        return $this;
    }

    public function done(): bool
    {
        return $this->status['done'];
    }

    private function recoverFromCrash(EventDispatcherInterface $eventDispatcher): bool
    {
        if ($this->lastJobExecution->isCrashed()) {
            return !$archived = $this->afterFailure(new WorkerDiedInTheLineOfDutyException(), $eventDispatcher);
        }

        return true;
    }

    private function afterFailure(\Throwable $exception, EventDispatcherInterface $eventDispatcher): bool
    {
        $this->lastJobExecution->failedWith($exception);
        $jobAfterFailure = new JobAfterFailure($this, $this->lastJobExecution);
        $this->retryPolicy->schedule($jobAfterFailure);
        $this->emit('job.ended', $eventDispatcher);
        $this->triggerOnWorkable('afterFailure', $exception);
        $jobAfterFailure->archiveIfNotScheduled();
        $archived = $jobAfterFailure->hasBeenArchived();
        if ($archived) {
            $this->emit('job.failure.last', $eventDispatcher);
            $this->triggerOnWorkable('afterLastFailure', $exception);
        }

        return $archived;
    }

    private function emit(string $eventType, EventDispatcherInterface $eventDispatcher): void
    {
        $event = new Event($this->export());
        $eventDispatcher->dispatch($event, $eventType);
        if ($this->workable instanceof EventListener) {
            $this->workable->onEvent($eventType, $event);
        }
    }

    /**
     * @param 'afterSuccess'|'afterFailure'|'afterLastFailure' $method
     */
    private function triggerOnWorkable(string $method, ?\Throwable $e = null): void
    {
        if ($this->workable instanceof Finalizable) {
            if (in_array($method, ['afterFailure', 'afterLastFailure'])) {
                assert(null !== $e, new \InvalidArgumentException("\$e cannot be null in $method"));
                $this->workable->$method($e);
            } else {
                $this->workable->$method();
            }

            if (in_array($method, ['afterSuccess', 'afterLastFailure'])) {
                $this->workable->finalize($e);
            }
        }
    }

    private function hasBeenScheduled(): bool
    {
        return array_key_exists('scheduled_at', $this->status);
    }

    private function scheduledAt(): ?Moment
    {
        if ($this->hasBeenScheduled()) {
            return T\MongoDate::toMoment($this->status['scheduled_at']);
        }

        return null;
    }

    /**
     * @return array{tags?: string[]}
     */
    private function tagsToUseFor(Workable $workable): array
    {
        $tagsToUse = [];
        if ($workable instanceof Taggable) {
            $tagsToUse = $workable->taggedAs();
        }
        if (isset($this->status['tags']) && !empty($this->status['tags'])) {
            $tagsToUse = array_merge($tagsToUse, $this->status['tags']);
        }
        if (!empty($tagsToUse)) {
            return ['tags' => array_values(array_unique($tagsToUse))];
        }

        return [];
    }

    /**
     * @return array{
     *     _id: ObjectId,
     *     done: bool,
     *     created_at: UTCDateTime,
     *     locked: bool,
     *     attempts: int,
     *     group: string,
     *     tags?: string[],
     *     workable: array{
     *         method: string,
     *     }
     * }
     */
    private static function initialize(): array
    {
        return array_merge(
            [
                '_id' => new ObjectId(),
                'done' => false,
                'created_at' => T\MongoDate::now(),
                'locked' => false,
                'attempts' => 0,
                'group' => 'generic',
            ],
            WorkableInJob::initialize(),
            RetryPolicyInJob::initialize(),
        );
    }

    /**
     * @param ObjectId[] $workers
     *
     * @return ?array{string, ObjectId[], ObjectId[]}
     */
    public static function pickReadyJobsForWorkers(MongoCollection $collection, string $worksOn, array $workers): ?array
    {
        $jobs = array_column(
            iterator_to_array(
                $collection
                    ->find(
                        Worker::canWorkOnAnyJobs($worksOn) ?
                        ['scheduled_at' => ['$lt' => T\MongoDate::now()],
                            'locked' => false,
                        ] :
                        ['scheduled_at' => ['$lt' => T\MongoDate::now()],
                            'locked' => false,
                            'group' => $worksOn,
                        ],
                        [
                            'projection' => ['_id' => 1],
                            'sort' => ['scheduled_at' => 1],
                            'limit' => count($workers),
                        ],
                    ),
            ),
            '_id',
        );

        if (count($jobs) > 0) {
            return [$worksOn, $workers, $jobs];
        }

        return null;
    }

    /**
     * @param ObjectId[] $excluded
     */
    public static function rollbackLockedNotIn(MongoCollection $collection, array $excluded): int
    {
        try {
            $result = $collection->updateMany(
                [
                    'locked' => true,
                    '_id' => ['$nin' => $excluded],
                ],
                [
                    '$set' => [
                        'locked' => false,
                        'last_execution.crashed' => true,
                    ],
                ],
            );

            return $result->getModifiedCount();
        } catch (BulkWriteException $e) {
            throw new \InvalidArgumentException('Not valid excluded jobs filter: ' . var_export($excluded, true), -1, $e);
        }
    }

    /**
     * @param ObjectId[] $jobs
     */
    public static function lockAll(MongoCollection $collection, array $jobs): void
    {
        $collection->updateMany(
            ['_id' => ['$in' => array_values($jobs)]],
            ['$set' => ['locked' => true]],
        );
    }
}
