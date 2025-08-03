<?php

namespace Recruiter\Acceptance;

use Recruiter\Worker\Repository;

class WorkerRepositoryTest extends BaseAcceptanceTestCase
{
    private Repository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new Repository(
            $this->recruiterDb,
            $this->recruiter,
        );
    }

    /**
     * @group acceptance
     */
    public function testRetireWorkerWithPid()
    {
        $this->givenWorkerWithPid(10);
        $this->assertEquals(1, $this->numberOfWorkers());
        $this->repository->retireWorkerWithPid(10);
        $this->assertEquals(0, $this->numberOfWorkers());
    }

    protected function givenWorkerWithPid($pid)
    {
        $document = ['pid' => $pid];
        $this->roster->insertOne($document);
    }
}
