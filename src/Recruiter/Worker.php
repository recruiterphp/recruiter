<?php

namespace Recruiter;

use MongoDB\BSON\ObjectId;
use MongoDB\Collection as MongoCollection;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Infrastructure\Memory\MemoryLimitExceededException;
use Recruiter\Worker\Repository;
use Timeless as T;
use Timeless\Interval;

class Worker
{
    public static function workFor(
        Recruiter $recruiter,
        Repository $repository,
        MemoryLimit $memoryLimit,
    ): self {
        $worker = new self(self::initialize(), $recruiter, $repository, $memoryLimit);
        $worker->save();

        return $worker;
    }

    public function __construct(private array $status, private readonly Recruiter $recruiter, private readonly Repository $repository, private readonly MemoryLimit $memoryLimit)
    {
    }

    public function id(): ObjectId
    {
        return $this->status['_id'];
    }

    public function pid(): int
    {
        return $this->status['pid'];
    }

    public function work(): string|false
    {
        $this->refresh();
        if ($this->hasBeenAssignedToDoSomething()) {
            $this->workOn(
                $job = $this->recruiter->scheduledJob(
                    $this->status['assigned_to'][(string) $this->status['_id']],
                ),
            );

            return (string) $job->id();
        } else {
            $this->stillHere();

            return false;
        }
    }

    public function export(): array
    {
        return $this->status;
    }

    public function updateWith(array $document): void
    {
        $this->status = self::fromMongoDocumentToInternalStatus($document);
    }

    public function workOnJobsGroupedAs(string $group): void
    {
        $this->status['work_on'] = $group;
        $this->save();
    }

    public function retireIfNotAssigned(): bool
    {
        return $this->repository->retireWorkerWithIdIfNotAssigned($this->status['_id']);
    }

    public function retire(): void
    {
        if ($this->hasBeenAssignedToDoSomething()) {
            throw new CannotRetireWorkerAtWorkException();
        }
        $this->repository->retireWorkerWithId($this->status['_id']);
    }

    private function stillHere(): void
    {
        $lastSeenAt = T\MongoDate::now();
        $this->status['last_seen_at'] = $lastSeenAt;
        $this->repository->atomicUpdate($this, ['last_seen_at' => $lastSeenAt]);
    }

    private function workOn($job): void
    {
        $this->beforeExecutionOf($job);
        $job->execute($this->recruiter->getEventDispatcher());
        $this->afterExecutionOf($job);
    }

    private function beforeExecutionOf($job): void
    {
        $this->status['working'] = true;
        $this->status['working_on'] = $job->id();
        $this->status['working_since'] = T\MongoDate::now();
        $this->status['last_seen_at'] = T\MongoDate::now();
        $this->save();
    }

    private function afterExecutionOf($job): void
    {
        try {
            $this->memoryLimit->ensure(memory_get_usage());
        } catch (MemoryLimitExceededException $e) {
            printf(
                '[WORKER][%d][%s] worker %s retired during execution of job `%s` after exception: `%s - %s`' . PHP_EOL,
                posix_getpid(),
                date('c'),
                $this->id(),
                $job->id(),
                $e::class,
                $e->getMessage(),
            );

            $this->retireAfterMemoryLimitIsExceeded();
            exit(1);
        }
        $this->status['working'] = false;
        $this->status['available'] = true;
        $this->status['available_since'] = T\MongoDate::now();
        $this->status['last_seen_at'] = T\MongoDate::now();
        unset($this->status['working_on']);
        unset($this->status['working_since']);
        unset($this->status['assigned_to']);
        unset($this->status['assigned_since']);
        $this->save();
    }

    private function retireAfterMemoryLimitIsExceeded()
    {
        $this->repository->retireWorkerWithId($this->id());
    }

    private function hasBeenAssignedToDoSomething(): bool
    {
        return array_key_exists('assigned_to', $this->status);
    }

    private function refresh(): void
    {
        $this->repository->refresh($this);
    }

    private function save(): void
    {
        $this->repository->save($this);
    }

    private static function fromMongoDocumentToInternalStatus($document)
    {
        return $document;
    }

    private static function initialize(): array
    {
        return [
            '_id' => new ObjectId(),
            'work_on' => '*',
            'available' => true,
            'available_since' => T\MongoDate::now(),
            'last_seen_at' => T\MongoDate::now(),
            'created_at' => T\MongoDate::now(),
            'working' => false,
            'pid' => getmypid(),
        ];
    }

    public static function canWorkOnAnyJobs(string $worksOn): bool
    {
        return '*' === $worksOn;
    }

    public static function pickAvailableWorkers(MongoCollection $collection, $workersPerUnit): array
    {
        $result = [];
        $workers = iterator_to_array($collection->find(['available' => true], ['projection' => ['_id' => 1, 'work_on' => 1]]));
        if (count($workers) > 0) {
            $unitsOfWorkers = array_group_by(
                $workers,
                fn ($worker) => $worker['work_on'],
            );
            foreach ($unitsOfWorkers as $workOn => $workersInUnit) {
                $workersInUnit = array_column($workersInUnit, '_id');
                $workersInUnit = array_slice($workersInUnit, 0, min(count($workersInUnit), $workersPerUnit));
                $result[] = [$workOn, $workersInUnit];
            }
        }

        return $result;
    }

    public static function tryToAssignJobsToWorkers(MongoCollection $collection, $jobs, $workers): array
    {
        $assignment = array_combine(
            array_map(fn ($id) => (string) $id, $workers),
            $jobs,
        );

        $result = $collection->updateMany(
            $where = ['_id' => ['$in' => array_values($workers)]],
            $update = ['$set' => [
                'available' => false,
                'assigned_to' => $assignment,
                'assigned_since' => T\MongoDate::now(),
            ]],
        );

        return [$assignment, $result->getModifiedCount()];
    }

    /**
     * @return ObjectId[]
     */
    public static function assignedJobs(MongoCollection $collection): array
    {
        $cursor = $collection->find([], ['projection' => ['assigned_to' => 1]]);
        $jobs = [];
        foreach ($cursor as $document) {
            if (array_key_exists('assigned_to', $document)) {
                $jobs = array_merge($jobs, array_values($document['assigned_to']));
            }
        }

        return array_values(array_unique($jobs));
    }

    public static function retireDeadWorkers(Repository $roster, \DateTimeImmutable $now, Interval $consideredDeadAfter)
    {
        $consideredDeadAt = $now->sub($consideredDeadAfter->toDateInterval());
        $deadWorkers = $roster->deadWorkers($consideredDeadAt);
        $jobsToReassign = [];
        foreach ($deadWorkers as $deadWorker) {
            $roster->retireWorkerWithId($deadWorker['_id']);
            if (array_key_exists('assigned_to', $deadWorker)) {
                if (array_key_exists((string) $deadWorker['_id'], $deadWorker['assigned_to'])) {
                    $jobsToReassign[] = $deadWorker['assigned_to'][(string) $deadWorker['_id']];
                }
            }
        }

        return $jobsToReassign;
    }
}
