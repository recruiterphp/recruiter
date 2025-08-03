<?php

namespace Recruiter\RetryPolicy;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;

class RetriableExceptionFilterTest extends TestCase
{
    private MockObject&RetryPolicy $filteredRetryPolicy;

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function setUp(): void
    {
        $this->filteredRetryPolicy = $this->createMock(RetryPolicy::class);
    }

    public function testCallScheduleOnRetriableException(): void
    {
        $exception = $this->createMock(\Exception::class);
        $classOfException = $exception::class;
        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy, [$classOfException]);

        $this->filteredRetryPolicy
            ->expects($this->once())
            ->method('schedule')
        ;

        $filter->schedule($this->jobFailedWithException($exception));
    }

    public function testDoNotCallScheduleOnNonRetriableException(): void
    {
        $exception = $this->createMock(\Exception::class);
        $classOfException = $exception::class;
        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy, [$classOfException]);

        $this->filteredRetryPolicy
            ->expects($this->never())
            ->method('schedule')
        ;

        $filter->schedule($this->jobFailedWithException(new \Exception('Test')));
    }

    public function testWhenExceptionIsNotRetriableThenArchiveTheJob(): void
    {
        $exception = $this->createMock(\Exception::class);
        $classOfException = $exception::class;
        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy, [$classOfException]);

        $job = $this->jobFailedWithException(new \Exception('Test'));
        $job->expects($this->once())
            ->method('archive')
            ->with('non-retriable-exception')
        ;

        $filter->schedule($job);
    }

    public function testAllExceptionsAreRetriableByDefault(): void
    {
        $this->filteredRetryPolicy
            ->expects($this->once())
            ->method('schedule')
        ;

        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy);
        $filter->schedule($this->jobFailedWithException(new \Exception('Test')));
    }

    public function testJobFailedWithSomethingThatIsNotAnException(): void
    {
        $jobAfterFailure = $this->jobFailedWithException(null);
        $jobAfterFailure
            ->expects($this->once())
            ->method('archive')
        ;

        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy);
        $filter->schedule($jobAfterFailure);
    }

    public function testExportFilteredRetryPolicy(): void
    {
        $this->filteredRetryPolicy
            ->expects($this->once())
            ->method('export')
            ->will($this->returnValue(['key' => 'value']))
        ;

        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy);

        $this->assertEquals(
            [
                'retriable_exceptions' => ['Exception'],
                'filtered_retry_policy' => [
                    'class' => $this->filteredRetryPolicy::class,
                    'parameters' => ['key' => 'value'],
                ],
            ],
            $filter->export(),
        );
    }

    public function testImportRetryPolicy(): void
    {
        $filteredRetryPolicy = new DoNotDoItAgain();
        $filter = new RetriableExceptionFilter($filteredRetryPolicy);
        $exported = $filter->export();

        $filter = RetriableExceptionFilter::import($exported);
        $filter->schedule($this->jobFailedWithException(new \Exception('Test')));

        $this->assertEquals($exported, $filter->export());
    }

    public function testRetriableExceptionsThatAreNotExceptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Only subclasses of Exception can be retriable exceptions, 'StdClass' is not");
        $retryPolicy = new DoNotDoItAgain();
        $notAnExceptionClass = 'StdClass';
        new RetriableExceptionFilter($retryPolicy, [$notAnExceptionClass]);
    }

    private function jobFailedWithException($exception)
    {
        $jobAfterFailure = $this
            ->getMockBuilder(JobAfterFailure::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $jobAfterFailure
            ->expects($this->any())
            ->method('causeOfFailure')
            ->will($this->returnValue($exception))
        ;

        return $jobAfterFailure;
    }
}
