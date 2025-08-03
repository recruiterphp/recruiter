<?php

namespace Recruiter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\Worker\Process;
use Recruiter\Worker\Repository;
use Sink\BlackHole;

class WorkerProcessTest extends TestCase
{
    private int $pid;
    private MockObject&Repository $repository;

    protected function setUp(): void
    {
        $this->pid = 4242;

        $this->repository = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    public function testIfNotAliveWhenIsNotAliveReturnsItself()
    {
        $process = $this->givenWorkerProcessDead();
        $this->assertInstanceOf(Process::class, $process->ifDead());
    }

    public function testIfNotAliveWhenIsAliveReturnsBlackHole()
    {
        $process = $this->givenWorkerProcessAlive();
        $this->assertInstanceOf(BlackHole::class, $process->ifDead());
    }

    public function testRetireWorkerIfNotAlive()
    {
        $this->repository
            ->expects($this->once())
            ->method('retireWorkerWithPid')
            ->with($this->pid)
        ;

        $process = $this->givenWorkerProcessDead();
        $process->cleanUp($this->repository);
    }

    public function testDoNotRetireWorkerIfAlive()
    {
        $this->repository
            ->expects($this->never())
            ->method('retireWorkerWithPid')
            ->with($this->pid)
        ;

        $process = $this->givenWorkerProcessAlive();
        $process->cleanUp($this->repository);
    }

    private function givenWorkerProcessAlive()
    {
        return $this->givenWorkerProcess(true);
    }

    private function givenWorkerProcessDead()
    {
        return $this->givenWorkerProcess(false);
    }

    private function givenWorkerProcess($alive)
    {
        $process = $this->getMockBuilder('Recruiter\Worker\Process')
            ->setMethods(['isAlive'])
            ->setConstructorArgs([$this->pid])
            ->getMock()
        ;

        $process->expects($this->any())
            ->method('isAlive')
            ->will($this->returnValue($alive))
        ;

        return $process;
    }
}
