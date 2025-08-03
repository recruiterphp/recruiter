<?php

namespace Recruiter;

use PHPUnit\Framework\MockObject\Exception;
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

    /**
     * @throws Exception
     */
    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $listener = $this->createPartialMock(EventListener::class, ['onEvent']);
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

    public function __construct(private readonly EventListener $listener)
    {
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
