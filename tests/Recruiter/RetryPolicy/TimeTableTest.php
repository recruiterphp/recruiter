<?php

declare(strict_types=1);

namespace Recruiter\RetryPolicy;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Timeless as T;

class TimeTableTest extends TestCase
{
    private TimeTable $scheduler;

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $this->scheduler = new TimeTable([
            '5 minutes ago' => '1 minute',
            '1 hour ago' => '5 minutes',
            '24 hours ago' => '1 hour',
        ]);
    }

    public function testShouldRescheduleInOneMinuteWhenWasCreatedLessThanFiveMinutesAgo(): void
    {
        $expectedToBeScheduledAt = T\minute(1)->fromNow()->toSecondPrecision();
        $wasCreatedAt = T\seconds(10)->ago();
        $job = $this->givenJobThat($wasCreatedAt);
        $job->expects($this->once())
            ->method('scheduleAt')
            ->with($this->equalTo($expectedToBeScheduledAt))
        ;
        $this->scheduler->schedule($job);
    }

    public function testShouldRescheduleInFiveMinutesWhenWasCreatedLessThanOneHourAgo(): void
    {
        $expectedToBeScheduledAt = T\minutes(5)->fromNow()->toSecondPrecision();
        $wasCreatedAt = T\minutes(30)->ago();
        $job = $this->givenJobThat($wasCreatedAt);
        $job->expects($this->once())
            ->method('scheduleAt')
            ->with($this->equalTo($expectedToBeScheduledAt))
        ;
        $this->scheduler->schedule($job);
    }

    public function testShouldRescheduleInFiveMinutesWhenWasCreatedLessThan24HoursAgo(): void
    {
        $expectedToBeScheduledAt = T\hour(1)->fromNow()->toSecondPrecision();
        $wasCreatedAt = T\hours(3)->ago();
        $job = $this->givenJobThat($wasCreatedAt);
        $job->expects($this->once())
            ->method('scheduleAt')
            ->with($this->equalTo($expectedToBeScheduledAt))
        ;
        $this->scheduler->schedule($job);
    }

    public function testShouldNotBeRescheduledWhenWasCreatedMoreThan24HoursAgo(): void
    {
        $job = $this->jobThatWasCreated('2 days ago');
        $job->expects($this->never())->method('scheduleAt');
        $this->scheduler->schedule($job);
    }

    /**
     * @throws Exception
     */
    public function testIsLastRetryReturnTrueIfJobWasCreatedMoreThanLastTimeSpen(): void
    {
        $job = $this->createMock(Job::class);
        $job->expects($this->any())
            ->method('createdAt')
            ->will($this->returnValue(T\hours(3)->ago()))
        ;

        $tt = new TimeTable([
            '1 minute ago' => '1 minute',
            '1 hour ago' => '1 minute',
        ]);
        $this->assertTrue($tt->isLastRetry($job));
    }

    public function testIsLastRetryReturnFalseIfJobWasCreatedLessThanLastTimeSpen(): void
    {
        $job = $this->createMock(Job::class);
        $job->expects($this->any())
            ->method('createdAt')
            ->will($this->returnValue(T\hours(3)->ago()))
        ;

        $tt = new TimeTable([
            '1 hour ago' => '1 minute',
            '24 hours ago' => '1 minute',
        ]);
        $this->assertFalse($tt->isLastRetry($job));
    }

    public function testInvalidTimeTableBecauseTimeWindow(): void
    {
        $this->expectException(\Exception::class);
        $tt = new TimeTable(['1 minute' => '1 second']);
    }

    public function testInvalidTimeTableBecauseRescheduleTime(): void
    {
        $this->expectException(\Exception::class);
        $tt = new TimeTable(['1 minute ago' => '1 second ago']);
    }

    public function testInvalidTimeTableBecauseRescheduleTimeIsGreaterThanTimeWindow(): void
    {
        $this->expectException(\Exception::class);
        $tt = new TimeTable(['1 minute ago' => '2 minutes']);
    }

    private function givenJobThat(T\Moment $wasCreatedAt): MockObject&JobAfterFailure
    {
        $job = $this->getMockBuilder(JobAfterFailure::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createdAt', 'scheduleAt'])
            ->getMock()
        ;
        $job->expects($this->any())
            ->method('createdAt')
            ->willReturn($wasCreatedAt)
        ;

        return $job;
    }

    private function jobThatWasCreated(string $relativeTime): MockObject&JobAfterFailure
    {
        $timestamp = strtotime($relativeTime);
        assert(false !== $timestamp);
        $wasCreatedAt = T\Moment::fromTimestamp($timestamp);
        $job = $this->getMockBuilder(JobAfterFailure::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createdAt', 'scheduleAt'])
            ->getMock()
        ;
        $job->expects($this->any())
            ->method('createdAt')
            ->willReturn($wasCreatedAt)
        ;

        return $job;
    }
}
