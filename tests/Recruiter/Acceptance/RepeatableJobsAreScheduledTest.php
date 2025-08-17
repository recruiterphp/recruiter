<?php

declare(strict_types=1);

namespace Recruiter\Acceptance;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Recruiter\Job;
use Recruiter\Job\Repository as JobsRepository;
use Recruiter\RetryPolicy\ExponentialBackoff;
use Recruiter\SchedulePolicy;
use Recruiter\Scheduler;
use Recruiter\Scheduler\Repository as SchedulersRepository;
use Recruiter\Workable\SampleRepeatableCommand;
use Timeless as T;
use Timeless\Moment;

class RepeatableJobsAreScheduledTest extends BaseAcceptanceTestCase
{
    use ArraySubsetAsserts;

    public function testARepeatableJobIsScheduledAtExpectedScheduledTime(): void
    {
        $expectedScheduleDate = strtotime('2019-05-16T14:00:00');
        $schedulePolicy = new FixedSchedulePolicy($expectedScheduleDate);

        $scheduler = $this->scheduleAJob('unique-urn', $schedulePolicy);
        $this->recruiterScheduleJobsNTimes(1);

        $jobs = $this->fetchScheduledJobs();

        $this->assertCount(1, $jobs);
        $jobData = $jobs[0]->export();

        self::assertArraySubset([
            'done' => false,
            'locked' => false,
            'attempts' => 0,
            'group' => 'generic',
            'workable' => [
                'class' => SampleRepeatableCommand::class,
                'parameters' => [],
                'method' => 'execute',
            ],
            'scheduled_at' => T\MongoDate::from(Moment::fromTimestamp($expectedScheduleDate)),
            'scheduled' => [
                'by' => [
                    'namespace' => 'scheduler',
                    'urn' => $scheduler->urn(),
                ],
                'executions' => 1,
            ],
            'retry_policy' => [
                'class' => ExponentialBackoff::class,
                'parameters' => [
                    'retry_how_many_times' => 2,
                    'seconds_to_initially_wait_before_retry' => 5,
                ],
            ],
        ], $jobData);
    }

    public function testOnlyASingleJobAreScheduledForTheSameSchedulingTime(): void
    {
        $expectedScheduleDate = strtotime('2019-05-16T14:00:00');
        $schedulePolicy = new FixedSchedulePolicy($expectedScheduleDate);

        $this->scheduleAJob('unique-urn', $schedulePolicy);

        $this->recruiterScheduleJobsNTimes(10);
        $jobs = $this->fetchScheduledJobs();

        $this->assertCount(1, $jobs);
        $jobData = $jobs[0]->export();
        assert(isset($jobData['scheduled_at']));

        $this->assertEquals(
            T\MongoDate::from(Moment::fromTimestamp($expectedScheduleDate)),
            $jobData['scheduled_at'],
        );
    }

    public function testAJobIsScheduledForEverySchedulingTime(): void
    {
        $expectedScheduleDates = [
            strtotime('2019-05-16T14:00:00'),
            strtotime('2019-05-17T14:00:00'),
        ];
        $schedulePolicy = new FixedSchedulePolicy($expectedScheduleDates);

        $this->scheduleAJob('unique-urn', $schedulePolicy);

        $this->recruiterScheduleJobsNTimes(2);
        $jobs = $this->fetchScheduledJobs();

        $this->assertCount(2, $jobs);
        $this->assertSame(2, $jobs[0]->export()['scheduled']['executions'] ?? 0);
        $this->assertSame(1, $jobs[1]->export()['scheduled']['executions'] ?? 0);
    }

    public function testANewJobIsNotScheduledIfItShouldBeUniqueAndTheOldOneIsStillRunning(): void
    {
        $schedulePolicy = new FixedSchedulePolicy([
            strtotime('2019-05-16T14:00:00'),
            strtotime('2019-05-17T14:00:00'),
        ]);

        $this->scheduleAJob('unique-urn', $schedulePolicy, true);

        $this->recruiterScheduleJobsNTimes(2);
        $jobs = $this->fetchScheduledJobs();

        $this->assertCount(1, $jobs);
    }

    public function testSchedulersAreUniqueOnUrn(): void
    {
        $aSchedulerAlreadyHaveSomeAttempts = 3;
        $this->IHaveAScheduleWithALongStory('unique-urn', $aSchedulerAlreadyHaveSomeAttempts);

        // Adding a scheduler with the same URN again
        $newSchedulingTime = strtotime('2023-02-18T17:00:00');
        $schedulePolicy = new FixedSchedulePolicy($newSchedulingTime);
        $this->scheduleAJob('unique-urn', $schedulePolicy, true);

        // Check that the scheduler keeps metadata intact
        $schedulers = $this->fetchSchedulers();
        $this->assertEquals(1, count($schedulers));

        // Check that job related data are updated
        $this->assertEquals($newSchedulingTime, $schedulers[0]->export()['schedule_policy']['parameters']['timestamps'][0]);
        $this->assertEquals(true, $schedulers[0]->export()['unique']);

        // Check that the scheduler keeps metadata intact
        $this->assertEquals($aSchedulerAlreadyHaveSomeAttempts, $schedulers[0]->export()['attempts']);
    }

    private function IHaveAScheduleWithALongStory(string $urn, int $attempts): void
    {
        $scheduleTimes = [];
        for ($i = 1; $i <= $attempts; ++$i) {
            $scheduleTimes[] = strtotime('2018-05-' . $i . 'T15:00:00');
        }
        $scheduleTimes = array_filter($scheduleTimes); // makes PHPStan happy

        $schedulePolicy = new FixedSchedulePolicy($scheduleTimes);
        $this->scheduleAJob($urn, $schedulePolicy);

        $this->recruiterScheduleJobsNTimes($attempts);
    }

    private function scheduleAJob(string $urn, ?SchedulePolicy $schedulePolicy = null, bool $unique = false): Scheduler
    {
        if (is_null($schedulePolicy)) {
            $schedulePolicy = new FixedSchedulePolicy(strtotime('2023-02-18T17:00:00'));
        }

        $scheduler = new SampleRepeatableCommand()
            ->asRepeatableJobOf($this->recruiter)
            ->repeatWithPolicy($schedulePolicy)
            ->retryWithPolicy(ExponentialBackoff::forTimes(2, 5))
            ->unique($unique)
        ;

        return $scheduler->create();
    }

    private function recruiterScheduleJobsNTimes(int $nth = 1): void
    {
        $i = 0;
        while ($i++ < $nth) {
            $this->recruiter->scheduleRepeatableJobs();
        }
    }

    /**
     * @return Job[]
     */
    private function fetchScheduledJobs(): array
    {
        $jobsRepository = new JobsRepository($this->recruiterDb);

        return $jobsRepository->all();
    }

    /**
     * @return Scheduler[]
     */
    private function fetchSchedulers(): array
    {
        $schedulersRepository = new SchedulersRepository($this->recruiterDb);

        return $schedulersRepository->all();
    }
}

class FixedSchedulePolicy implements SchedulePolicy
{
    /**
     * @var int[]
     */
    private array $timestamps;

    /**
     * @param array<int>|int $timestamps
     */
    public function __construct(array|int $timestamps, private int $index = 0)
    {
        if (!is_array($timestamps)) {
            $timestamps = [$timestamps];
        }

        $this->timestamps = $timestamps;
    }

    public function next(): Moment
    {
        $moment = Moment::fromTimestamp($this->timestamps[$this->index]);

        $this->index = min($this->index + 1, count($this->timestamps) - 1);

        return $moment;
    }

    public static function import(array $parameters): SchedulePolicy
    {
        return new self($parameters['timestamps'], $parameters['index']);
    }

    public function export(): array
    {
        return [
            'timestamps' => $this->timestamps,
            'index' => $this->index,
        ];
    }
}
