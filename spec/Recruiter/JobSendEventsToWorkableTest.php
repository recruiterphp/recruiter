<?php

namespace Recruiter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\Job\Event;
use Recruiter\Job\EventListener;
use Recruiter\Job\Repository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobSendEventsToWorkableTest extends TestCase
{
    private MockObject&Repository $repository;
    private MockObject&EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $listener = new EventListenerSpy();
        $workable = new WorkableThatIsAlsoAnEventListener($listener);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->dispatcher);

        $events = $listener->events;
        $this->assertCount(3, $events);
        $this->assertSame('job.started', $events[0][0]);
        $this->assertSame('job.ended', $events[1][0]);
        $this->assertSame('job.failure.last', $events[2][0]);
    }
}

class WorkableThatIsAlsoAnEventListener implements Workable, EventListener
{
    use WorkableBehaviour;

    public function __construct(private readonly EventListener $listener)
    {
        $this->parameters = [];
    }

    public function onEvent($channel, Event $ev)
    {
        return $this->listener->onEvent($channel, $ev);
    }

    public function execute()
    {
        throw new \Exception();
    }
}

class EventListenerSpy implements EventListener
{
    public array $events = [];

    public function onEvent($channel, Event $ev): void
    {
        $this->events[] = [$channel, $ev];
    }
}
