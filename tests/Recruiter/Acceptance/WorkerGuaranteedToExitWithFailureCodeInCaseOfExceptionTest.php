<?php

namespace Recruiter\Acceptance;

use Recruiter\Workable\ExitsAbruptly;

class WorkerGuaranteedToExitWithFailureCodeInCaseOfExceptionTest extends BaseAcceptanceTestCase
{
    /**
     * @group acceptance
     */
    public function testInCaseOfExceptionTheExitCodeOfWorkerProcessIsNotZero(): void
    {
        new ExitsAbruptly()
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute()
        ;

        $worker = $this->startWorker();
        $workerProcess = $worker[0];
        $this->waitForNumberOfWorkersToBe(1);
        [$assignments, $_] = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->waitForNumberOfWorkersToBe(0, $seconds = 10);

        $status = proc_get_status($workerProcess);
        $this->assertNotEquals(0, $status['exitcode']);
    }
}
