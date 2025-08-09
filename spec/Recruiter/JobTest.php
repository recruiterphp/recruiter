<?php

namespace Recruiter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Job\Repository;
use Recruiter\Workable\AlwaysFail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobTest extends TestCase
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

    public function testRetryStatisticsOnFirstExecution(): void
    {
        $job = Job::around(new AlwaysFail(), $this->repository);
        $retryStatistics = $job->retryStatistics();
        $this->assertArrayHasKey('job_id', $retryStatistics);
        $this->assertIsString($retryStatistics['job_id']);
        $this->assertArrayHasKey('retry_number', $retryStatistics);
        $this->assertEquals(0, $retryStatistics['retry_number']);
        $this->assertArrayHasKey('last_execution', $retryStatistics);
        $this->assertNull($retryStatistics['last_execution']);
    }

    /**
     * @depends testRetryStatisticsOnFirstExecution
     */
    public function testRetryStatisticsOnSubsequentExecutions(): void
    {
        $job = Job::around(new AlwaysFail(), $this->repository);
        // maybe make the argument optional
        $job->execute($this->createMock(EventDispatcherInterface::class));
        $job = Job::import($job->export(), $this->repository);
        $retryStatistics = $job->retryStatistics();
        $this->assertEquals(1, $retryStatistics['retry_number']);
        $this->assertArrayHasKey('last_execution', $retryStatistics);
        $lastExecution = $retryStatistics['last_execution'];
        $this->assertIsArray($lastExecution);
        $this->assertArrayHasKey('started_at', $lastExecution);
        $this->assertArrayHasKey('ended_at', $lastExecution);
        $this->assertArrayHasKey('class', $lastExecution);
        $this->assertArrayHasKey('message', $lastExecution);
        $this->assertArrayHasKey('trace', $lastExecution);
        $this->assertEquals("Sorry, I'm good for nothing", $lastExecution['message']);
        $this->assertMatchesRegularExpression('/.*AlwaysFail->execute.*/', $lastExecution['trace']);
    }

    public function testArrayAsGroupIsNotAllowed(): void
    {
        $this->expectException(\RuntimeException::class);
        $memoryLimit = new MemoryLimit(1);
        $job = Job::around(new AlwaysFail(), $this->repository);
        $job->inGroup(['test']);
    }
}
