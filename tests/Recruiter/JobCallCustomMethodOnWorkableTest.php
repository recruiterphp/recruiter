<?php

declare(strict_types=1);

namespace Recruiter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\Job\Repository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobCallCustomMethodOnWorkableTest extends TestCase
{
    private MockObject&Workable $workable;
    private MockObject&Repository $repository;
    private Job $job;

    protected function setUp(): void
    {
        $this->workable = $this
            ->getMockBuilder(DummyWorkableWithSendCustomMethod::class)
            ->onlyMethods(['export', 'import', 'asJobOf', 'send'])
            ->getMock()
        ;

        $this->repository = $this
            ->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->job = Job::around($this->workable, $this->repository);
    }

    public function testConfigureMethodToCallOnWorkable(): void
    {
        $this->workable->expects($this->once())->method('send');
        $this->job->methodToCallOnWorkable('send');
        $this->job->execute($this->createMock(EventDispatcherInterface::class));
    }

    public function testRaiseExceptionWhenConfigureMethodToCallOnWorkableThatDoNotExists(): void
    {
        $this->expectException(\Exception::class);
        $this->job->methodToCallOnWorkable('methodThatDoNotExists');
    }

    public function testCustomMethodIsSaved(): void
    {
        $this->job->methodToCallOnWorkable('send');
        $jobExportedToDocument = $this->job->export();
        $this->assertArrayHasKey('workable', $jobExportedToDocument);
        $this->assertArrayHasKey('method', $jobExportedToDocument['workable']);
        $this->assertEquals('send', $jobExportedToDocument['workable']['method']);
    }

    public function testCustomMethodIsConservedAfterImport(): void
    {
        $workable = new DummyWorkableWithSendCustomMethod();
        $job = Job::around($workable, $this->repository);
        $job->methodToCallOnWorkable('send');
        $jobExportedToDocument = $job->export();
        $jobImported = Job::import($jobExportedToDocument, $this->repository);
        $jobExportedToDocument = $job->export();
        $this->assertArrayHasKey('workable', $jobExportedToDocument);
        $this->assertArrayHasKey('method', $jobExportedToDocument['workable']);
        $this->assertEquals('send', $jobExportedToDocument['workable']['method']);
    }
}

class DummyWorkableWithSendCustomMethod extends BaseWorkable
{
    public function send()
    {
    }
}
