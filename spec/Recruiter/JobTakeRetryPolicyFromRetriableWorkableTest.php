<?php

namespace Recruiter;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Recruiter\Job\Repository;
use Recruiter\RetryPolicy\BaseRetryPolicy;
use Timeless as T;
use Recruiter\RetryPolicy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobTakeRetryPolicyFromRetriableWorkableTest extends TestCase
{
    private MockObject&Repository $repository;
    private MockObject&EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    /**
     * @throws Exception
     */
    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $retryPolicy = $this->createMock(BaseRetryPolicy::class);
        $retryPolicy->expects($this->once())->method('schedule');

        $workable = new WorkableThatIsAlsoRetriable($retryPolicy);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->eventDispatcher);
    }
}

class WorkableThatIsAlsoRetriable implements Workable, Retriable
{
    use WorkableBehaviour;

    public function __construct(private readonly RetryPolicy $retryWithPolicy)
    {
    }

    public function retryWithPolicy(): RetryPolicy
    {
        return $this->retryWithPolicy;
    }

    public function execute()
    {
        throw new \Exception();
    }
}
