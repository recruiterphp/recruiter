<?php

namespace Recruiter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Timeless as T;

class JobToScheduleTest extends TestCase
{
    private T\ClockInterface $clock;
    private MockObject&Job $job;

    protected function setUp(): void
    {
        $this->clock = T\clock()->stop();
        $this->job = $this
            ->getMockBuilder(Job::class)
            ->disableOriginalConstructor()
            ->getMock()
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
        $this->job
            ->expects($this->any())
            ->method('id')
            ->will($this->returnValue('42'))
        ;

        $this->assertEquals(
            '42',
            new JobToSchedule($this->job)->execute(),
        );
    }
}
