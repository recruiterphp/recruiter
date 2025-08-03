<?php

namespace Recruiter\RetryPolicy;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;
use Timeless as T;

class SelectByExceptionTest extends TestCase
{
    public function testCanBeBuilt(): void
    {
        SelectByException::create()
             ->when(\InvalidArgumentException::class)->then(new DoNotDoItAgain())
             ->when(\LogicException::class)->then(new DoNotDoItAgain())
             ->build()
        ;
    }

    public function testCanBeExportedAndImported(): void
    {
        $retryPolicy = SelectByException::create()
             ->when(\InvalidArgumentException::class)->then(new DoNotDoItAgain())
             ->when(\LogicException::class)->then(new DoNotDoItAgain())
             ->build()
        ;

        $retryPolicyExported = $retryPolicy->export();
        $retryPolicyImported = SelectByException::import($retryPolicyExported);

        $this->assertEquals($retryPolicy, $retryPolicyImported);
        $this->assertEquals($retryPolicyExported, $retryPolicyImported->export());
    }

    public function testSelectByException(): void
    {
        $exception = new \InvalidArgumentException('something');
        $retryPolicy = new SelectByException([
            new RetriableException($exception::class, RetryForever::afterSeconds(10)),
        ]);

        $job = $this->jobFailedWith($exception);
        $job->expects($this->once())
            ->method('scheduleIn')
            ->with(T\seconds(10))
        ;

        $retryPolicy->schedule($job);
    }

    /**
     * @throws \Exception
     */
    public function testDefaultDoNotSchedule(): void
    {
        $exception = new \Exception('something');
        $retryPolicy = new SelectByException([
            new RetriableException(\InvalidArgumentException::class, RetryForever::afterSeconds(10)),
        ]);

        $job = $this->jobFailedWith($exception);
        $job->expects($this->never())->method('scheduleIn');
        $job->expects($this->once())->method('archive');

        $retryPolicy->schedule($job);
    }

    private function jobFailedWith(\Throwable $exception): MockObject&JobAfterFailure
    {
        $job = $this->getMockBuilder(JobAfterFailure::class)->disableOriginalConstructor()->getMock();
        $job->expects($this->any())
            ->method('causeOfFailure')
            ->willReturn($exception)
        ;

        return $job;
    }
}
