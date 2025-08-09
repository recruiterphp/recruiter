<?php

namespace Recruiter\Acceptance;

use Recruiter\Workable\AlwaysFail;
use Recruiter\Workable\FactoryMethodCommand;
use Timeless as T;

class SynchronousExecutionTest extends BaseAcceptanceTestCase
{
    public function testJobsAreExecutedInOrderOfScheduling(): void
    {
        $this->enqueueAnAnswerJob(43, T\now()->after(T\seconds(30)));

        $this->enqueueAnAnswerJob(42, T\now());

        $report = $this->recruiter->flushJobsSynchronously();

        $this->assertFalse($report->isThereAFailure());
        $results = $report->toArray();
        $this->assertEquals(42, current($results)->result());
        $this->assertEquals(43, end($results)->result());
    }

    public function testAReportIsReturnedInOrderToSortOutIfAnErrorOccured(): void
    {
        new AlwaysFail()
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute()
        ;

        $report = $this->recruiter->flushJobsSynchronously();

        $this->assertTrue($report->isThereAFailure());
    }

    private function enqueueAnAnswerJob(int $answer, T\Moment $scheduledAt): void
    {
        FactoryMethodCommand::from('Recruiter\Acceptance\SyncronousExecutionTestDummyObject::create')
            ->answer($answer)
            ->asJobOf($this->recruiter)
            ->scheduleAt($scheduledAt)
            ->inBackground()
            ->execute()
        ;
    }
}

class SyncronousExecutionTestDummyObject
{
    public static function create(): self
    {
        return new self();
    }

    /**
     * @template T1
     *
     * @param T1 $value
     *
     * @return T1
     */
    public function answer(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param array<string, mixed> $retryStatistics
     */
    public function myNeedyMethod(array $retryStatistics): int
    {
        return $retryStatistics['retry_number'];
    }
}
