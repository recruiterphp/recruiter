<?php

declare(strict_types=1);

namespace Recruiter;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\Job\Repository;
use Recruiter\RetryPolicy\DoNotDoItAgain;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobToBePassedRetryStatisticsTest extends TestCase
{
    private MockObject&Repository $repository;

    protected function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @throws Exception
     */
    public function testTakeRetryPolicyFromRetriableInstance(): void
    {
        $workable = new WorkableThatUsesRetryStatistics();

        $job = Job::around($workable, $this->repository);
        $job->execute($this->createMock(EventDispatcherInterface::class));
        $this->assertTrue($job->done(), 'Job requiring retry statistics was not executed correctly: ' . var_export($job->export(), true));
    }
}

class WorkableThatUsesRetryStatistics implements Workable, Retriable
{
    use WorkableBehaviour;

    public function retryWithPolicy(): RetryPolicy
    {
        return new DoNotDoItAgain();
    }

    public function execute(array $retryStatistics)
    {
    }
}
