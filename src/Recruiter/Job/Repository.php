<?php

namespace Recruiter\Job;

use MongoDB;
use MongoDB\BSON\ObjectId;
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
            throw new \Exception("Unable to find scheduled job with ObjectId('{$id}')");
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
            throw new \Exception("Unable to find archived job with ObjectId('{$id}')");
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

    public function releaseAll($jobIds): int
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

    public function queued(
        $group = null,
        ?T\Moment $at = null,
        ?T\Moment $from = null,
        array $query = [],
    ): int {
        if (null === $at) {
            $at = T\now();
        }

        $query['scheduled_at']['$lte'] = T\MongoDate::from($at);

        if (null !== $from) {
            $query['scheduled_at']['$gt'] = T\MongoDate::from($from);
        }

        if (null !== $group) {
            $query['group'] = $group;
        }

        return $this->scheduled->count($query);
    }

    public function postponed($group = null, ?T\Moment $at = null, array $query = []): int
    {
        if (null === $at) {
            $at = T\now();
        }

        $query['scheduled_at']['$gt'] = T\MongoDate::from($at);

        if (null !== $group) {
            $query['group'] = $group;
        }

        return $this->scheduled->countDocuments($query);
    }

    public function scheduledCount($group = null, array $query = []): int
    {
        if (null !== $group) {
            $query['group'] = $group;
        }

        return $this->scheduled->countDocuments($query);
    }

    public function queuedGroupedBy($field, array $query = [], $group = null): array
    {
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
        foreach ($cursor as $r) {
            $distinctAndCount[$r['_id']] = $r['count'];
        }

        return $distinctAndCount;
    }

    public function recentHistory($group = null, ?T\Moment $at = null, array $query = []): array
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
        $secondsToConsiderJobAsSlow = 5,
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
            'archived',
        ) + $this->countRecentArchivedOrScheduledJobsWithManyAttempts(
            $lowerLimit,
            $upperLimit,
            'scheduled',
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

    public function recentJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
    ): array {
        $archived = $this->map(
            $this->recentArchivedOrScheduledJobsWithManyAttempts(
                $lowerLimit,
                $upperLimit,
                'archived',
            ),
        );
        $scheduled = $this->map(
            $this->recentArchivedOrScheduledJobsWithManyAttempts(
                $lowerLimit,
                $upperLimit,
                'scheduled',
            ),
        );

        return array_merge($archived, $scheduled);
    }

    public function slowRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $secondsToConsiderJobAsSlow = 5,
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

    private function slowArchivedRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $secondsToConsiderJobAsSlow,
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

    private function slowScheduledRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $secondsToConsiderJobAsSlow,
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
        $collectionName,
    ): int {
        return count($this->recentArchivedOrScheduledJobsWithManyAttempts(
            $lowerLimit,
            $upperLimit,
            $collectionName,
        )->toArray());
    }

    private function recentArchivedOrScheduledJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $collectionName,
    ) {
        return $this->{$collectionName}->find([
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
     */
    private function map(CursorInterface $cursor): array
    {
        $jobs = [];
        foreach ($cursor as $document) {
            $jobs[] = Job::import($document, $this);
        }

        return $jobs;
    }
}
