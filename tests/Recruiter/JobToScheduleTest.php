<?php

declare(strict_types=1);

namespace Recruiter;

use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Timeless as T;

class JobToScheduleTest extends TestCase
{
    private T\ClockInterface $clock;
    private MockObject&Job $job;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->clock = T\clock()->stop();
        $this->job = $this->createMock(Job::class);
        $this->job
            ->expects($this->any())
            ->method('id')
            ->willReturnCallback(fn () => new ObjectId())
        ;
    }

    protected function tearDown(): void
    {
        $this->clock->start();
    }

    public function testInBackgroundShouldScheduleJobNow(): void
    {
        $this->job
            ->expects($this->once())
            ->method('scheduleAt')
            ->with(
                $this->equalTo($this->clock->now()),
            )
        ;

        new JobToSchedule($this->job)
            ->inBackground()
            ->execute()
        ;
    }

    public function testScheduledInShouldScheduleInCertainAmountOfTime(): void
    {
        $amountOfTime = T\minutes(10);
        $this->job
            ->expects($this->once())
            ->method('scheduleAt')
            ->with(
                $this->equalTo($amountOfTime->fromNow()),
            )
        ;

        new JobToSchedule($this->job)
            ->scheduleIn($amountOfTime)
            ->execute()
        ;
    }

    public function testConfigureRetryPolicy(): void
    {
        $doNotDoItAgain = new RetryPolicy\DoNotDoItAgain();

        $this->job
            ->expects($this->once())
            ->method('retryWithPolicy')
            ->with($doNotDoItAgain)
        ;

        new JobToSchedule($this->job)
            ->inBackground()
            ->retryWithPolicy($doNotDoItAgain)
            ->execute()
        ;
    }

    public function tesShortcutToConfigureJobToNotBeRetried(): void
    {
        $this->job
            ->expects($this->once())
            ->method('retryWithPolicy')
            ->with($this->isInstanceOf(RetryPolicy\DoNotDoItAgain::class))
        ;

        new JobToSchedule($this->job)
            ->inBackground()
            ->doNotRetry()
            ->execute()
        ;
    }

    public function testShouldNotExecuteJobWhenScheduled(): void
    {
        $this->job
            ->expects($this->once())
            ->method('save')
        ;

        $this->job
            ->expects($this->never())
            ->method('execute')
        ;

        new JobToSchedule($this->job)
            ->inBackground()
            ->execute()
        ;
    }

    public function testShouldExecuteJobWhenNotScheduled(): void
    {
        $this->job
            ->expects($this->never())
            ->method('scheduleAt')
        ;

        $this->job
            ->expects($this->once())
            ->method('execute')
        ;

        new JobToSchedule($this->job)->execute();
    }

    public function testConfigureMethodToCallOnWorkableInJob(): void
    {
        $this->job
            ->expects($this->once())
            ->method('methodToCallOnWorkable')
            ->with('send')
        ;

        new JobToSchedule($this->job)
            ->send()
        ;
    }

    public function testReturnsJobId(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{24}$/',
            new JobToSchedule($this->job)->execute(),
        );
    }
}
