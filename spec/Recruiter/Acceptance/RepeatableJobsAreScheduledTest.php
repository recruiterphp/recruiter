<?php

namespace Recruiter\Acceptance;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Recruiter\Job\Repository as JobsRepository;
use Recruiter\RetryPolicy\ExponentialBackoff;
use Recruiter\SchedulePolicy;
use Recruiter\Scheduler\Repository as SchedulersRepository;
use Recruiter\Workable\SampleRepeatableCommand;
use Timeless as T;
use Timeless\Moment;

class RepeatableJobsAreScheduledTest extends BaseAcceptanceTestCase
{
    use ArraySubsetAsserts;

    public function testARepeatableJobIsScheduledAtExpectedScheduledTime()
    {
        $expectedScheduleDate = strtotime('2019-05-16T14:00:00');
        $schedulePolicy = new FixedSchedulePolicy($expectedScheduleDate);

        $scheduler = $this->scheduleAJob('unique-urn', $schedulePolicy);
        $this->recruiterScheduleJobsNTimes(1);

        $jobs = $this->fetchScheduledJobs();

        $this->assertEquals(1, count($jobs));
        $jobData = $jobs[0]->export();

        self::assertArraySubset([
            'done' => false,
            'locked' => false,
            'attempts' => 0,
            'group' => 'generic',
            'workable' => [
                'class' => 'Recruiter\Workable\SampleRepeatableCommand',
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
                'class' => 'Recruiter\RetryPolicy\ExponentialBackoff',
                'parameters' => [
                    'retry_how_many_times' => 2,
                    'seconds_to_initially_wait_before_retry' => 5,
                ],
            ],
        ], $jobData);
    }

    public function testOnlyASingleJobAreScheduledForTheSameSchedulingTime()
    {
        $expectedScheduleDate = strtotime('2019-05-16T14:00:00');
        $schedulePolicy = new FixedSchedulePolicy($expectedScheduleDate);

        $this->scheduleAJob('unique-urn', $schedulePolicy);

        $this->recruiterScheduleJobsNTimes(10);
        $jobs = $this->fetchScheduledJobs();

        $this->assertEquals(1, count($jobs));
        $jobData = $jobs[0]->export();

        $this->assertEquals(
            T\MongoDate::from(Moment::fromTimestamp($expectedScheduleDate)),
            $jobs[0]->export()['scheduled_at'],
        );
    }

    public function testAJobIsScheduledForEverySchedulingTime()
    {
        $expectedScheduleDates = [
            strtotime('2019-05-16T14:00:00'),
            strtotime('2019-05-17T14:00:00'),
        ];
        $schedulePolicy = new FixedSchedulePolicy($expectedScheduleDates);

        $this->scheduleAJob('unique-urn', $schedulePolicy);

        $this->recruiterScheduleJobsNTimes(2);
        $jobs = $this->fetchScheduledJobs();

        $this->assertEquals(2, count($jobs));
        $this->assertEquals(2, $jobs[0]->export()['scheduled']['executions']);
        $this->assertEquals(1, $jobs[1]->export()['scheduled']['executions']);
    }

    public function testANewJobIsNotScheduledIfItShouldBeUniqueAndTheOldOneIsStillRunning()
    {
        $schedulePolicy = new FixedSchedulePolicy([
            strtotime('2019-05-16T14:00:00'),
            strtotime('2019-05-17T14:00:00'),
        ]);

        $this->scheduleAJob('unique-urn', $schedulePolicy, true);

        $this->recruiterScheduleJobsNTimes(2);
        $jobs = $this->fetchScheduledJobs();

        $this->assertEquals(1, count($jobs));
    }

    public function testSchedulersAreUniqueOnUrn()
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

    private function IHaveAScheduleWithALongStory(string $urn, $attempts)
    {
        $scheduleTimes = [];
        for ($i = 1; $i <= $attempts; ++$i) {
            $scheduleTimes[] = strtotime('2018-05-' . $i . 'T15:00:00');
        }

        $schedulePolicy = new FixedSchedulePolicy($scheduleTimes);
        $this->scheduleAJob($urn, $schedulePolicy);

        $this->recruiterScheduleJobsNTimes($attempts);
    }

    private function scheduleAJob(string $urn, ?SchedulePolicy $schedulePolicy = null, bool $unique = false)
    {
        if (is_null($schedulePolicy)) {
            $schedulePolicy = new FixedSchedulePolicy(strtotime('2023-02-18T17:00:00'));
        }

        $scheduler = (new SampleRepeatableCommand())
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

    private function fetchScheduledJobs()
    {
        $jobsRepository = new JobsRepository($this->recruiterDb);

        return $jobsRepository->all();
    }

    private function fetchSchedulers()
    {
        $schedulersRepository = new SchedulersRepository($this->recruiterDb);

        return $schedulersRepository->all();
    }
}

class FixedSchedulePolicy implements SchedulePolicy
{
    private array $timestamps;
    private int $index;

    public function __construct($timestamps, $index = 0)
    {
        if (!is_array($timestamps)) {
            $timestamps = [$timestamps];
        }

        $this->timestamps = $timestamps;
        $this->index = $index;
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
