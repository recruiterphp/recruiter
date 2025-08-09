<?php

namespace Recruiter;

use MongoDB;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Timeless as T;
use Timeless\Interval;
use Timeless\Moment;

class Recruiter
{
    private readonly Job\Repository $jobs;
    private readonly Worker\Repository $workers;
    private readonly Scheduler\Repository $scheduler;
    private readonly EventDispatcher $eventDispatcher;

    public function __construct(private readonly MongoDB\Database $db)
    {
        $this->jobs = new Job\Repository($this->db);
        $this->workers = new Worker\Repository($this->db);
        $this->scheduler = new Scheduler\Repository($this->db);
        $this->eventDispatcher = new EventDispatcher();
    }

    public function hire(MemoryLimit $memoryLimit): Worker
    {
        return Worker::workFor($this, $this->workers, $memoryLimit);
    }

    public function jobOf(Workable $workable): JobToSchedule
    {
        return new JobToSchedule(
            Job::around($workable, $this->jobs),
        );
    }

    public function repeatableJobOf(Repeatable $repeatable): Scheduler
    {
        return Scheduler::around($repeatable, $this->scheduler);
    }

    public function queued(): int
    {
        return $this->jobs->queued();
    }

    public function scheduled(): int
    {
        return $this->jobs->scheduledCount();
    }

    public function queuedGroupedBy($field, array $query = [], $group = null): array
    {
        return $this->jobs->queuedGroupedBy($field, $query, $group);
    }

    #[\Deprecated(message: 'use the method `analytics` instead')]
    public function statistics($group = null, ?Moment $at = null, array $query = []): array
    {
        return $this->analytics($group, $at, $query);
    }

    /**
     * @return array<string,mixed>
     */
    public function analytics($group = null, ?Moment $at = null, array $query = []): array
    {
        $totalsScheduledJobs = $this->jobs->scheduledCount($group, $query);
        $queued = $this->jobs->queued($group, $at, $at?->before(T\hour(24)), $query);
        $postponed = $this->jobs->postponed($group, $at, $query);

        return array_merge(
            [
                'jobs' => [
                    'queued' => $queued,
                    'postponed' => $postponed,
                    'zombies' => $totalsScheduledJobs - ($queued + $postponed),
                ],
            ],
            $this->jobs->recentHistory($group, $at, $query),
        );
    }

    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * @step
     *
     * @return int how many
     */
    public function rollbackLockedJobs(): int
    {
        $assignedJobs = Worker::assignedJobs($this->db->selectCollection('roster'));

        return Job::rollbackLockedNotIn($this->db->selectCollection('scheduled'), $assignedJobs);
    }

    /**
     * @step
     */
    public function bye(): void
    {
    }

    public function assignJobsToWorkers(): array
    {
        return $this->assignLockedJobsToWorkers($this->bookJobsForWorkers());
    }

    public function scheduleRepeatableJobs(): void
    {
        $schedulers = $this->scheduler->all();
        foreach ($schedulers as $scheduler) {
            $scheduler->schedule($this->jobs);
        }
    }

    /**
     * @step
     */
    public function bookJobsForWorkers(): array
    {
        $roster = $this->db->selectCollection('roster');
        $scheduled = $this->db->selectCollection('scheduled');
        $workersPerUnit = 42;

        $bookedJobs = [];
        foreach (Worker::pickAvailableWorkers($roster, $workersPerUnit) as $resultRow) {
            [$worksOn, $workers] = $resultRow;

            $result = Job::pickReadyJobsForWorkers($scheduled, $worksOn, $workers);
            if ($result) {
                [$worksOn, $workers, $jobs] = $result;
                [$assignments, $jobs, $workers] = $this->combineJobsWithWorkers($jobs, $workers);

                Job::lockAll($scheduled, $jobs);
                $bookedJobs[] = [$jobs, $workers];
            }
        }

        return $bookedJobs;
    }

    /**
     * @step
     */
    public function assignLockedJobsToWorkers(array $bookedJobs): array
    {
        $assignments = [];
        $totalActualAssignments = 0;
        $roster = $this->db->selectCollection('roster');
        foreach ($bookedJobs as $row) {
            [$jobs, $workers] = $row;
            [$newAssignments, $actualAssignmentsNumber] = Worker::tryToAssignJobsToWorkers($roster, $jobs, $workers);
            if (array_intersect_key($assignments, $newAssignments)) {
                throw new \RuntimeException('Conflicting assignments: current were ' . var_export($assignments, true) . ' and we want to also assign ' . var_export($newAssignments, true));
            }
            $assignments = array_merge(
                $assignments,
                $newAssignments,
            );
            $totalActualAssignments += $actualAssignmentsNumber;
        }

        return [
            array_map(fn ($value) => (string) $value, $assignments),
            $totalActualAssignments,
        ];
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }

    /**
     * @step
     *
     * @return int how many jobs were unlocked as a result
     */
    public function retireDeadWorkers(\DateTimeImmutable $now, Interval $consideredDeadAfter): int
    {
        return $this->jobs->releaseAll(
            $jobsAssignedToDeadWorkers = Worker::retireDeadWorkers($this->workers, $now, $consideredDeadAfter),
        );
    }

    public function flushJobsSynchronously(): SynchronousExecutionReport
    {
        $report = [];

        foreach ($this->jobs->all() as $job) {
            $report[(string) $job->id()] = $job->execute($this->eventDispatcher);
        }

        return SynchronousExecutionReport::fromArray($report);
    }

    public function createCollectionsAndIndexes(): void
    {
        $this->db->selectCollection('scheduled')->createIndex(
            [
                'group' => 1,
                'locked' => 1,
                'scheduled_at' => 1,
            ],
            ['background' => true],
        );
        $this->db->selectCollection('scheduled')->createIndex(
            [
                'locked' => 1,
                'scheduled_at' => 1,
            ],
            ['background' => true],
        );
        $this->db->selectCollection('scheduled')->createIndex(
            [
                'locked' => 1,
            ],
            ['background' => true],
        );
        $this->db->selectCollection('scheduled')->createIndex(
            [
                'tags' => 1,
            ],
            ['background' => true, 'sparse' => true],
        );

        $this->db->selectCollection('archived')->createIndex(
            [
                'created_at' => 1,
            ],
            ['background' => true],
        );
        $this->db->selectCollection('archived')->createIndex(
            [
                'created_at' => 1,
                'group' => 1,
            ],
            ['background' => true],
        );
        $this->db->selectCollection('archived')->createIndex(
            [
                'last_execution.ended_at' => 1,
            ],
            ['background' => true],
        );

        $this->db->selectCollection('roster')->createIndex(
            [
                'available' => 1,
            ],
            ['background' => true],
        );
        $this->db->selectCollection('roster')->createIndex(
            [
                'last_seen_at' => 1,
            ],
            ['background' => true],
        );
    }

    private function combineJobsWithWorkers($jobs, $workers): array
    {
        $assignments = min(count($workers), count($jobs));
        $workers = array_slice($workers, 0, $assignments);
        $jobs = array_slice($jobs, 0, $assignments);

        return [$assignments, $jobs, $workers];
    }
}
