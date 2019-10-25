<?php
namespace Recruiter\RetryPolicy;

use InvalidArgumentException;
use LogicException;
use Exception;
use PHPUnit\Framework\TestCase;
use Recruiter\RetryPolicy;
use Timeless as T;

class SelectByExceptionTest extends TestCase
{
    public function testCanBeBuilt()
    {
        $retryPolicy = SelectByException::create()
                     ->when(InvalidArgumentException::class)->then(new DoNotDoItAgain())
                     ->when(LogicException::class)->then(new DoNotDoItAgain())
                     ->build();

        $this->assertInstanceOf(RetryPolicy::class, $retryPolicy);
    }

    public function testCanBeExportedAndImported()
    {
        $retryPolicy = SelectByException::create()
                     ->when(InvalidArgumentException::class)->then(new DoNotDoItAgain())
                     ->when(LogicException::class)->then(new DoNotDoItAgain())
                     ->build();

        $retryPolicyExported = $retryPolicy->export();
        $retryPolicyImported = SelectByException::import($retryPolicyExported);

        $this->assertEquals($retryPolicy, $retryPolicyImported);
        $this->assertEquals($retryPolicyExported, $retryPolicyImported->export());
    }

    public function testSelectByException()
    {
        $exception = new InvalidArgumentException('something');
        $retryPolicy = new SelectByException([
            new RetriableException(get_class($exception), RetryForever::afterSeconds(10))
        ]);

        $job = $this->jobFailedWith($exception);
        $job->expects($this->once())
            ->method('scheduleIn')
            ->with(T\seconds(10));

        $retryPolicy->schedule($job);
    }

    public function testDefaultDoNotSchedule()
    {
        $exception = new Exception('something');
        $retryPolicy = new SelectByException([
            new RetriableException(InvalidArgumentException::class, RetryForever::afterSeconds(10))
        ]);

        $job = $this->jobFailedWith($exception);
        $job->expects($this->never())->method('scheduleIn');
        $job->expects($this->once())->method('archive');

        $retryPolicy->schedule($job);
    }

    private function jobFailedWith(Exception $exception)
    {
        $job = $this->getMockBuilder('Recruiter\JobAfterFailure')->disableOriginalConstructor()->getMock();
        $job->expects($this->any())
            ->method('causeOfFailure')
            ->will($this->returnValue($exception));
        return $job;
    }
}
