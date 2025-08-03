<?php

namespace Recruiter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Timeless\Interval;
use Timeless\Clock;
use Timeless\Moment;
use Timeless as T;

class CleanerTest extends TestCase
{
    private T\ClockInterface $clock;
    private Moment $now;
    private MockObject $jobRepository;
    private Cleaner $cleaner;
    private Interval $interval;

    protected function setUp(): void
    {
        $this->clock = T\clock()->stop();
        $this->now = $this->clock->now();

        $this->jobRepository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->cleaner = new Cleaner($this->jobRepository);

        $this->interval = Interval::parse('10s');
    }

    protected function tearDown(): void
    {
        T\clock()->start();
    }

    public function testShouldCreateCleaner()
    {
        $this->assertInstanceOf('Recruiter\Cleaner', $this->cleaner);
    }

    public function testDelegatesTheCleanupOfArchivedJobsToTheJobsRepository()
    {
        $expectedUpperLimit = $this->now->before($this->interval);

        $this->jobRepository
            ->expects($this->once())
            ->method('cleanArchived')
            ->with($expectedUpperLimit)
            ->will($this->returnValue($jobsCleaned = 10));

        $this->assertEquals(
            $jobsCleaned,
            $this->cleaner->cleanArchived($this->interval)
        );
    }
}
