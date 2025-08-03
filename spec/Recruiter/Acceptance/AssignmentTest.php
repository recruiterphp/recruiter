<?php
namespace Recruiter\Acceptance;

use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Workable\LazyBones;

class AssignmentTest extends BaseAcceptanceTestCase
{
    public function testAJobCanBeAssignedAndExecuted(): void
    {
        $memoryLimit = new MemoryLimit('64MB');
        LazyBones::waitForMs(200, 100)
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $worker = $this->recruiter->hire($memoryLimit);
        list ($assignments, $totalNumber) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->assertEquals(1, $totalNumber);
        $this->assertTrue((bool) $worker->work());
    }
}
