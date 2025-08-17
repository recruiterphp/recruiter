<?php

declare(strict_types=1);

namespace Recruiter\Job;

use MongoDB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use Recruiter\Job;
use Timeless as T;

class Repository
{
    private readonly Collection $scheduled;
    private readonly Collection $archived;

    public function __construct(MongoDB\Database $db)
    {
        $this->scheduled = $db->selectCollection('scheduled');
        $this->archived = $db->selectCollection('archived');
    }

    /**
     * @return Job[]
     */
    public function all(): array
    {
        return $this->map(
            $this->scheduled->find([], [
                'sort' => ['scheduled_at' => -1],
            ]),
        );
    }

    public function archiveAll(): void
    {
        foreach ($this->all() as $job) {
            $this->archive($job);
        }
    }

    public function scheduled(string|ObjectId $id): Job
    {
        if (is_string($id)) {
            $id = new ObjectId($id);
        }

        $found = $this->map($this->scheduled->find(['_id' => $id]));

        if (0 === count($found)) {
            throw new \InvalidArgumentException("Unable to find scheduled job with ObjectId('{$id}')");
        }

        return $found[0];
    }

    public function archived(string|ObjectId $id): Job
    {
        if (is_string($id)) {
            $id = new ObjectId($id);
        }

        $found = $this->map($this->archived->find(['_id' => $id]));

        if (0 === count($found)) {
            throw new \InvalidArgumentException("Unable to find archived job with ObjectId('{$id}')");
        }

        return $found[0];
    }

    public function save(Job $job): void
    {
        $document = $job->export();
        $this->scheduled->replaceOne(
            ['_id' => $document['_id']],
            $document,
            ['upsert' => true],
        );
    }

    public function archive(Job $job): void
    {
        $document = $job->export();
        $this->scheduled->deleteOne(['_id' => $document['_id']]);
        $this->archived->replaceOne(['_id' => $document['_id']], $document, ['upsert' => true]);
    }

    /**
     * @param ObjectId[] $jobIds
     */
    public function releaseAll(array $jobIds): int
    {
        $result = $this->scheduled->updateMany(
            ['_id' => ['$in' => $jobIds]],
            ['$set' => ['locked' => false, 'last_execution.crashed' => true]],
        );

        return $result->getModifiedCount();
    }

    public function countArchived(): int
    {
        return $this->archived->countDocuments();
    }

    public function cleanArchived(T\Moment $upperLimit): int
    {
        $documents = $this->archived->find(
            [
                'last_execution.ended_at' => [
                    '$lte' => T\MongoDate::from($upperLimit),
                ],
            ],
            ['projection' => ['_id' => 1]],
        );

        $deleted = 0;
        foreach ($documents as $document) {
            assert(is_array($document) && isset($document['_id']));
            $this->archived->deleteOne(['_id' => $document['_id']]);
            ++$deleted;
        }

        return $deleted;
    }

    public function cleanScheduled(T\Moment $upperLimit): int
    {
        $result = $this->scheduled->deleteMany([
            'created_at' => [
                '$lte' => T\MongoDate::from($upperLimit),
            ],
        ]);

        return $result->getDeletedCount();
    }

    /**
     * @param array<string, mixed> $query
     */
    public function queued(
        ?string $group = null,
        ?T\Moment $at = null,
        ?T\Moment $from = null,
        array $query = [],
    ): int {
        if (null === $at) {
            $at = T\now();
        }

        // Make PHPStan happy
        $query['scheduled_at'] ??= [];
        assert(is_array($query['scheduled_at']));

        $query['scheduled_at']['$lte'] = T\MongoDate::from($at);

        if (null !== $from) {
            $query['scheduled_at']['$gt'] = T\MongoDate::from($from);
        }

        if (null !== $group) {
            $query['group'] = $group;
        }

        return $this->scheduled->countDocuments($query);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function postponed(?string $group = null, ?T\Moment $at = null, array $query = []): int
    {
        if (null === $at) {
            $at = T\now();
        }

        // Make PHPStan happy
        $query['scheduled_at'] ??= [];
        assert(is_array($query['scheduled_at']));

        $query['scheduled_at']['$gt'] = T\MongoDate::from($at);

        if (null !== $group) {
            $query['group'] = $group;
        }

        return $this->scheduled->countDocuments($query);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function scheduledCount(?string $group = null, array $query = []): int
    {
        if (null !== $group) {
            $query['group'] = $group;
        }

        return $this->scheduled->countDocuments($query);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, int>
     */
    public function queuedGroupedBy(string $field, array $query = [], ?string $group = null): array
    {
        // Make PHPStan happy
        $query['scheduled_at'] ??= [];
        assert(is_array($query['scheduled_at']));

        $query['scheduled_at']['$lte'] = T\MongoDate::from(T\now());
        if (null !== $group) {
            $query['group'] = $group;
        }

        $cursor = $this->scheduled->aggregate($pipeline = [
            ['$match' => $query],
            ['$group' => [
                '_id' => '$' . $field,
                'count' => ['$sum' => 1],
            ]],
        ]);

        $distinctAndCount = [];
        /** @var array{_id: string, count: int} $r */
        foreach ($cursor as $r) {
            $distinctAndCount[$r['_id']] = $r['count'];
        }

        return $distinctAndCount;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{
     *     throughput: array{
     *         value: float,
     *         value_per_second: float,
     *     },
     *     latency: array{
     *         average: float,
     *     },
     *     execution_time: array{
     *         average: float,
     *     },
     * }
     */
    public function recentHistory(?string $group = null, ?T\Moment $at = null, array $query = []): array
    {
        if (null === $at) {
            $at = T\now();
        }
        $lastMinute = array_merge(
            $query,
            [
                'last_execution.ended_at' => [
                    '$gt' => T\MongoDate::from($at->before(T\minute(1))),
                    '$lte' => T\MongoDate::from($at),
                ],
            ],
        );
        if (null !== $group) {
            $lastMinute['group'] = $group;
        }
        $cursor = $this->archived->aggregate($pipeline = [
            ['$match' => $lastMinute],
            ['$project' => [
                'latency' => ['$subtract' => [
                    '$last_execution.started_at',
                    '$last_execution.scheduled_at',
                ]],
                'execution_time' => ['$subtract' => [
                    '$last_execution.ended_at',
                    '$last_execution.started_at',
                ]],
            ]],
            ['$group' => [
                '_id' => 1,
                'throughput' => ['$sum' => 1],
                'latency' => ['$avg' => '$latency'],
                'execution_time' => ['$avg' => '$execution_time'],
            ]],
        ]);

        /** @var array{_id: 1, throughput: int, latency: numeric, execution_time: numeric}[] $documents */
        $documents = $cursor->toArray();
        if (0 === count($documents)) {
            $throughputPerMinute = 0.0;
            $averageLatency = 0.0;
            $averageExecutionTime = 0;
        } elseif (1 === count($documents)) {
            $throughputPerMinute = (float) $documents[0]['throughput'];
            $averageLatency = $documents[0]['latency'] / 1000;
            $averageExecutionTime = $documents[0]['execution_time'] / 1000;
        } else {
            throw new \RuntimeException('Result was not ok: ' . var_export($documents, true));
        }

        return [
            'throughput' => [
                'value' => $throughputPerMinute,
                'value_per_second' => $throughputPerMinute / 60.0,
            ],
            'latency' => [
                'average' => $averageLatency,
            ],
            'execution_time' => [
                'average' => $averageExecutionTime,
            ],
        ];
    }

    public function countSlowRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        int $secondsToConsiderJobAsSlow = 5,
    ): int {
        return count(
            $this->slowArchivedRecentJobs(
                $lowerLimit,
                $upperLimit,
                $secondsToConsiderJobAsSlow,
            ),
        ) + count(
            $this->slowScheduledRecentJobs(
                $lowerLimit,
                $upperLimit,
                $secondsToConsiderJobAsSlow,
            ),
        );
    }

    public function countRecentJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
    ): int {
        return $this->countRecentArchivedOrScheduledJobsWithManyAttempts(
            $lowerLimit,
            $upperLimit,
            $this->archived,
        ) + $this->countRecentArchivedOrScheduledJobsWithManyAttempts(
            $lowerLimit,
            $upperLimit,
            $this->scheduled,
        );
    }

    public function countDelayedScheduledJobs(T\Moment $lowerLimit): int
    {
        return $this->scheduled->count([
            'scheduled_at' => [
                '$lte' => T\MongoDate::from($lowerLimit),
            ],
        ]);
    }

    /**
     * @return Job[]
     */
    public function delayedScheduledJobs(T\Moment $lowerLimit): array
    {
        return $this->map(
            $this->scheduled->find([
                'scheduled_at' => [
                    '$lte' => T\MongoDate::from($lowerLimit),
                ],
            ]),
        );
    }

    /**
     * @return Job[]
     */
    public function recentJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
    ): array {
        $archived = $this->map(
            $this->recentArchivedOrScheduledJobsWithManyAttempts(
                $lowerLimit,
                $upperLimit,
                $this->archived,
            ),
        );
        $scheduled = $this->map(
            $this->recentArchivedOrScheduledJobsWithManyAttempts(
                $lowerLimit,
                $upperLimit,
                $this->scheduled,
            ),
        );

        return array_merge($archived, $scheduled);
    }

    /**
     * @return Job[]
     */
    public function slowRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        int $secondsToConsiderJobAsSlow = 5,
    ): array {
        $archived = [];
        $archivedArray = $this->slowArchivedRecentJobs(
            $lowerLimit,
            $upperLimit,
            $secondsToConsiderJobAsSlow,
        );
        foreach ($archivedArray as $archivedJob) {
            $archived[] = Job::import($archivedJob, $this);
        }
        $scheduled = [];
        $scheduledArray = $this->slowScheduledRecentJobs(
            $lowerLimit,
            $upperLimit,
            $secondsToConsiderJobAsSlow,
        );
        foreach ($scheduledArray as $scheduledJob) {
            $scheduled[] = Job::import($scheduledJob, $this);
        }

        return array_merge($archived, $scheduled);
    }

    /**
     * @return array<mixed>
     */
    private function slowArchivedRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        int $secondsToConsiderJobAsSlow,
    ): array {
        return $this->archived->aggregate([
            [
                '$match' => [
                    'last_execution.ended_at' => [
                        '$gte' => T\MongoDate::from($lowerLimit),
                    ],
                ],
            ],
            [
                '$project' => [
                    '_id' => '$_id',
                    'execution_time' => [
                        '$subtract' => [
                            '$last_execution.ended_at',
                            '$last_execution.started_at',
                        ],
                    ],
                    'done' => '$done',
                    'created_at' => '$created_at',
                    'locked' => '$locked',
                    'attempts' => '$attempts',
                    'group' => '$group',
                    'workable' => '$workable',
                    'tags' => '$tags',
                    'scheduled_at' => '$scheduled_at',
                    'last_execution' => '$last_execution',
                    'retry_policy' => '$retry_policy',
                ],
            ],
            [
                '$match' => [
                    'execution_time' => [
                        '$gt' => $secondsToConsiderJobAsSlow * 1000,
                    ],
                ],
            ],
        ])->toArray();
    }

    /**
     * @return array{
     *     _id: ObjectId,
     *     execution_time: int,
     *     done: bool,
     *     created_at: UTCDateTime,
     *     locked: bool,
     *     attempts: int,
     *     group: string,
     *     workable: array{
     *         method: string,
     *         class?: class-string,
     *         parameters?: array<mixed>,
     *     },
     *     tags?: string[],
     *     scheduled_at?: UTCDateTime,
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
     *     retry_policy?: array<string, mixed>,
     * }[]
     */
    private function slowScheduledRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        int $secondsToConsiderJobAsSlow,
    ): array {
        return $this->scheduled->aggregate([
            [
                '$match' => [
                    'scheduled_at' => [
                        '$gte' => T\MongoDate::from($lowerLimit),
                        '$lte' => T\MongoDate::from($upperLimit),
                    ],
                    'last_execution.started_at' => [
                        '$exists' => true,
                    ],
                    'last_execution.ended_at' => [
                        '$exists' => true,
                    ],
                ],
            ],
            [
                '$project' => [
                    '_id' => '$_id',
                    'execution_time' => [
                        '$subtract' => [
                            '$last_execution.ended_at',
                            '$last_execution.started_at',
                        ],
                    ],
                    'done' => '$done',
                    'created_at' => '$created_at',
                    'locked' => '$locked',
                    'attempts' => '$attempts',
                    'group' => '$group',
                    'workable' => '$workable',
                    'tags' => '$tags',
                    'scheduled_at' => '$scheduled_at',
                    'last_execution' => '$last_execution',
                    'retry_policy' => '$retry_policy',
                ],
            ],
            [
                '$match' => [
                    'execution_time' => [
                        '$gt' => $secondsToConsiderJobAsSlow * 1000,
                    ],
                ],
            ],
        ])->toArray();
    }

    private function countRecentArchivedOrScheduledJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        Collection $collection,
    ): int {
        return count($this->recentArchivedOrScheduledJobsWithManyAttempts(
            $lowerLimit,
            $upperLimit,
            $collection,
        )->toArray());
    }

    private function recentArchivedOrScheduledJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        Collection $collection,
    ): CursorInterface {
        return $collection->find([
            'last_execution.ended_at' => [
                '$gte' => T\MongoDate::from($lowerLimit),
                '$lte' => T\MongoDate::from($upperLimit),
            ],
            'attempts' => [
                '$gt' => 1,
            ],
        ]);
    }

    /**
     * @return array<Job>
     *
     * @throws \Exception
     */
    private function map(CursorInterface $cursor): array
    {
        $jobs = [];
        foreach ($cursor as $document) {
            assert(is_array($document));
            $jobs[] = Job::import($document, $this);
        }

        return $jobs;
    }
}
