<?php

namespace Recruiter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
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
            ->getMock();

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $listener = $this->createPartialMock('StdClass', ['onEvent']);
        $listener
            ->expects($this->exactly(3))
            ->method('onEvent')
            ->withConsecutive(
                [$this->equalTo('job.started'), $this->anything()],
                [$this->equalTo('job.ended'), $this->anything()],
                [$this->equalTo('job.failure.last'), $this->anything()]
            );
        $workable = new WorkableThatIsAlsoAnEventListener($listener);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->dispatcher);
    }
}

class WorkableThatIsAlsoAnEventListener implements Workable, EventListener
{
    use WorkableBehaviour;

    public function __construct($listener)
    {
        $this->listener = $listener;
    }

    public function onEvent($channel, Event $e)
    {
        return $this->listener->onEvent($channel, $e);
    }

    public function execute()
    {
        throw new \Exception();
    }
}
