<?php

declare(strict_types=1);

namespace Recruiter\Acceptance;

use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Job\Event;
use Recruiter\RetryPolicy\RetryManyTimes;
use Recruiter\Workable\AlwaysFail;
use Recruiter\Workable\AlwaysSucceed;

class HooksTest extends BaseAcceptanceTestCase
{
    private MemoryLimit $memoryLimit;
    private array $events;

    #[\Override]
    protected function setUp(): void
    {
        $this->memoryLimit = new MemoryLimit('64MB');
        parent::setUp();
    }

    public function testAfterFailureWithoutRetryEventIsFired(): void
    {
        $this->events = [];
        $this->recruiter
            ->getEventDispatcher()
            ->addListener(
                'job.failure.last',
                function (Event $event): void {
                    $this->events[] = $event;
                },
            )
        ;

        $job = new AlwaysFail()
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute()
        ;

        $worker = $this->recruiter->hire($this->memoryLimit);
        $this->recruiter->assignJobsToWorkers();
        $worker->work();

        $this->assertEquals(1, count($this->events));
        $this->assertInstanceOf(Event::class, $this->events[0]);
        $this->assertEquals('not-scheduled-by-retry-policy', $this->events[0]->export()['why']);
    }

    public function testAfterLastFailureEventIsFired(): void
    {
        $this->events = [];
        $this->recruiter
            ->getEventDispatcher()
            ->addListener(
                'job.failure.last',
                function (Event $event): void {
                    $this->events[] = $event;
                },
            )
        ;

        $job = new AlwaysFail()
            ->asJobOf($this->recruiter)
            ->retryWithPolicy(RetryManyTimes::forTimes(1, 0))
            ->inBackground()
            ->execute()
        ;

        $runAJob = function ($howManyTimes, $worker): void {
            for ($i = 0; $i < $howManyTimes;) {
                [$_, $assigned] = $this->recruiter->assignJobsToWorkers();
                $worker->work();
                if ($assigned > 0) {
                    ++$i;
                }
            }
        };

        $worker = $this->recruiter->hire($this->memoryLimit);
        $runAJob(2, $worker);

        $this->assertEquals(1, count($this->events));
        $this->assertInstanceOf(Event::class, $this->events[0]);
        $this->assertEquals('tried-too-many-times', $this->events[0]->export()['why']);
    }

    public function testJobStartedIsFired(): void
    {
        $this->events = [];
        $this->recruiter
            ->getEventDispatcher()
            ->addListener(
                'job.started',
                function (Event $event): void {
                    $this->events[] = $event;
                },
            )
        ;

        $job = new AlwaysSucceed()
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute()
        ;

        $worker = $this->recruiter->hire($this->memoryLimit);
        $this->recruiter->assignJobsToWorkers();
        $worker->work();

        $this->assertEquals(1, count($this->events));
        $this->assertInstanceOf(Event::class, $this->events[0]);
    }

    public function testJobEndedIsFired(): void
    {
        $this->events = [];
        $this->recruiter
            ->getEventDispatcher()
            ->addListener(
                'job.ended',
                function (Event $event): void {
                    $this->events[] = $event;
                },
            )
        ;

        new AlwaysSucceed()
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute()
        ;

        new AlwaysFail()
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute()
        ;

        $worker = $this->recruiter->hire($this->memoryLimit);
        $this->recruiter->assignJobsToWorkers();
        $worker->work();
        $this->recruiter->assignJobsToWorkers();
        $worker->work();

        $this->assertEquals(2, count($this->events));
        $this->assertInstanceOf(Event::class, $this->events[0]);
        $this->assertInstanceOf(Event::class, $this->events[1]);
    }
}
