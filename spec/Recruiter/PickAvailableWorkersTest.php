<?php

namespace Recruiter;

use MongoDB\BSON\Int64;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use MongoDB\Driver\Server;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PickAvailableWorkersTest extends TestCase
{
    private MockObject&Collection $repository;
    private int $workersPerUnit;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(Collection::class);
        $this->workersPerUnit = 42;
    }

    public function testNoWorkersAreFound()
    {
        $this->withNoAvailableWorkers();

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        $this->assertEquals([], $picked);
    }

    public function testFewWorkersWithNoSpecificSkill(): void
    {
        $callbackHasBeenCalled = false;
        $this->withAvailableWorkers(['*' => 3]);

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        [$worksOn, $workers] = $picked[0];
        $this->assertEquals('*', $worksOn);
        $this->assertCount(3, $workers);
    }

    public function testFewWorkersWithSameSkill()
    {
        $callbackHasBeenCalled = false;
        $this->withAvailableWorkers(['send-emails' => 3]);

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        [$worksOn, $workers] = $picked[0];
        $this->assertEquals('send-emails', $worksOn);
        $this->assertEquals(3, count($workers));
    }

    public function testFewWorkersWithSomeDifferentSkills()
    {
        $this->withAvailableWorkers(['send-emails' => 3, 'count-transactions' => 3]);
        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        $allSkillsGiven = [];
        $totalWorkersGiven = 0;
        foreach ($picked as $pickedRow) {
            [$worksOn, $workers] = $pickedRow;
            $allSkillsGiven[] = $worksOn;
            $totalWorkersGiven += count($workers);
        }
        $this->assertArrayAreEquals(['send-emails', 'count-transactions'], $allSkillsGiven);
        $this->assertEquals(6, $totalWorkersGiven);
    }

    public function testMoreWorkersThanAllowedPerUnit()
    {
        $this->withAvailableWorkers(['send-emails' => $this->workersPerUnit + 10]);

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        $totalWorkersGiven = 0;
        foreach ($picked as $pickedRow) {
            [$worksOn, $workers] = $pickedRow;
            $totalWorkersGiven += count($workers);
        }
        $this->assertEquals($this->workersPerUnit, $totalWorkersGiven);
    }

    /**
     * @throws Exception
     */
    private function withAvailableWorkers($workers): void
    {
        $workersThatShouldBeFound = [];
        foreach ($workers as $skill => $quantity) {
            for ($counter = 0; $counter < $quantity; ++$counter) {
                $workerId = new ObjectId();
                $workersThatShouldBeFound[(string) $workerId] = [
                    '_id' => $workerId,
                    'work_on' => $skill,
                ];
            }
        }

        $this->repository
            ->expects($this->any())
            ->method('find')
            ->willReturn(new FakeCursor($workersThatShouldBeFound))
        ;
    }

    private function withNoAvailableWorkers()
    {
        $this->repository
            ->expects($this->any())
            ->method('find')
            ->willReturn(new FakeCursor())
        ;
    }

    private function assertArrayAreEquals($expected, $given)
    {
        sort($expected);
        sort($given);
        $this->assertEquals($expected, $given);
    }
}

class FakeCursor implements CursorInterface, \Iterator
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = array_values($data);
    }

    public function getId(): Int64
    {
        return new Int64(42);
    }

    public function getServer(): Server
    {
        throw new \LogicException('Not implemented');
    }

    public function isDead(): bool
    {
        throw new \LogicException('Not implemented');
    }

    public function setTypeMap(array $typemap): void
    {
        throw new \LogicException('Not implemented');
    }

    public function toArray(): array
    {
        throw new \LogicException('Not implemented');
    }

    public function current(): object|array|null
    {
        return current($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    public function key(): ?int
    {
        return key($this->data);
    }

    public function valid(): bool
    {
        return null !== key($this->data);
    }

    public function rewind(): void
    {
        reset($this->data);
    }
}
