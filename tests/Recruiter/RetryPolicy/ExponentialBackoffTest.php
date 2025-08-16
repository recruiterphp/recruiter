<?php

declare(strict_types=1);

namespace Recruiter\RetryPolicy;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\JobAfterFailure;
use Timeless as T;

class ExponentialBackoffTest extends TestCase
{
    public function testOnTheFirstFailureUsesTheSpecifiedInterval(): void
    {
        $job = $this->jobExecutedFor(1);
        $retryPolicy = new ExponentialBackoff(100, T\seconds(5));

        $job->expects($this->once())
            ->method('scheduleIn')
            ->with(T\seconds(5))
        ;
        $retryPolicy->schedule($job);
    }

    public function testAfterEachFailureDoublesTheAmountOfTimeToWaitBetweenRetries(): void
    {
        $job = $this->jobExecutedFor(2);
        $retryPolicy = new ExponentialBackoff(100, T\seconds(5));

        $job->expects($this->once())
            ->method('scheduleIn')
            ->with(T\seconds(10))
        ;
        $retryPolicy->schedule($job);
    }

    public function testAfterTooManyFailuresGivesUp(): void
    {
        $job = $this->jobExecutedFor(101);
        $retryPolicy = new ExponentialBackoff(100, T\seconds(5));

        $job->expects($this->once())
            ->method('archive')
            ->with('tried-too-many-times')
        ;
        $retryPolicy->schedule($job);
    }

    public function testCanBeCreatedByTargetingAMaximumInterval(): void
    {
        $this->assertEquals(
            ExponentialBackoff::forAnInterval(1025, T\seconds(1)),
            new ExponentialBackoff(10, 1),
        );
    }

    private function jobExecutedFor(int $times): MockObject&JobAfterFailure
    {
        $job = $this->getMockBuilder(JobAfterFailure::class)->disableOriginalConstructor()->getMock();
        $job->expects($this->any())
            ->method('numberOfAttempts')
            ->willReturn($times)
        ;

        return $job;
    }
}
