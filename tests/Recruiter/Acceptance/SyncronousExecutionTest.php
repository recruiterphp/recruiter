<?php

declare(strict_types=1);

namespace Recruiter\Acceptance;

use Recruiter\Workable\AlwaysFail;
use Recruiter\Workable\FactoryMethodCommand;
use Timeless as T;

class SyncronousExecutionTest extends BaseAcceptanceTestCase
{
    public function testJobsAreExecutedInOrderOfScheduling(): void
    {
        $this->enqueueAnAnswerJob(43, T\now()->after(T\seconds(30)));

        $this->enqueueAnAnswerJob(42, T\now());

        $report = $this->recruiter->flushJobsSynchronously();

        $this->assertFalse($report->isThereAFailure());
        $results = $report->toArray();
        $this->assertCount(2, $results);
        $values = array_values($results);
        $this->assertEquals(42, $values[0]->result());
        $this->assertEquals(43, $values[1]->result());
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

    private function enqueueAnAnswerJob(mixed $answer, T\Moment $scheduledAt): void
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

    public function answer(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param array{retry_number: int} $retryStatistics
     */
    public function myNeedyMethod(array $retryStatistics): int
    {
        return $retryStatistics['retry_number'];
    }
}
