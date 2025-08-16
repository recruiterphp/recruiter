<?php

declare(strict_types=1);

namespace Recruiter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\Job\Repository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FinalizerMethodsAreCalledWhenWorkableImplementsFinalizerInterfaceTest extends TestCase
{
    private MockObject&Repository $repository;
    private MockObject&EventDispatcherInterface $dispatcher;
    private ListenerSpy $listener;

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->listener = new ListenerSpy();
    }

    public function testFinalizableFailureMethodsAreCalledWhenJobFails(): void
    {
        $exception = new \Exception('job was failed');

        $workable = new FinalizableWorkable(function () use ($exception): void {
            throw $exception;
        }, $this->listener);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->dispatcher);

        $calls = $this->listener->calls;
        $this->assertCount(3, $calls);

        $this->assertSame('afterFailure', $calls[0][0]);
        $this->assertSame($exception, $calls[0][1]);

        $this->assertSame('afterLastFailure', $calls[1][0]);
        $this->assertSame($exception, $calls[1][1]);

        $this->assertSame('finalize', $calls[2][0]);
        $this->assertSame($exception, $calls[2][1]);
    }

    public function testFinalizableSuccessfullMethodsAreCalledWhenJobIsDone(): void
    {
        $workable = new FinalizableWorkable(fn () => true, $this->listener);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->dispatcher);

        $calls = $this->listener->calls;
        $this->assertCount(2, $calls);
        $this->assertSame('afterSuccess', $calls[0][0]);
        $this->assertSame('finalize', $calls[1][0]);
    }
}

class ListenerSpy
{
    public array $calls = [];

    public function methodWasCalled(string $name, ?\Throwable $exception = null): void
    {
        $this->calls[] = [$name, $exception];
    }
}

class FinalizableWorkable implements Workable, Finalizable
{
    use WorkableBehaviour;
    use FinalizableBehaviour;

    private $whatToDo;

    public function __construct(callable $whatToDo, private $listener)
    {
        $this->parameters = [];
        $this->whatToDo = $whatToDo;
    }

    public function execute(): mixed
    {
        $whatToDo = $this->whatToDo;

        return $whatToDo();
    }

    public function afterSuccess(): void
    {
        $this->listener->methodWasCalled(__FUNCTION__);
    }

    public function afterFailure(\Throwable $e): void
    {
        $this->listener->methodWasCalled(__FUNCTION__, $e);
    }

    public function afterLastFailure(\Throwable $e): void
    {
        $this->listener->methodWasCalled(__FUNCTION__, $e);
    }

    public function finalize(?\Throwable $e = null): void
    {
        $this->listener->methodWasCalled(__FUNCTION__, $e);
    }
}
