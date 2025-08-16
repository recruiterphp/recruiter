<?php

declare(strict_types=1);

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
            ->execute()
        ;

        $worker = $this->recruiter->hire($memoryLimit);
        [$assignments, $totalNumber] = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->assertEquals(1, $totalNumber);
        $this->assertTrue((bool) $worker->work());
    }
}
