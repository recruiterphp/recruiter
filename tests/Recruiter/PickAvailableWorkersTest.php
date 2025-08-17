<?php

declare(strict_types=1);

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

    public function testNoWorkersAreFound(): void
    {
        $this->withNoAvailableWorkers();

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        $this->assertEquals([], $picked);
    }

    /**
     * @throws Exception
     */
    public function testFewWorkersWithNoSpecificSkill(): void
    {
        $callbackHasBeenCalled = false;
        $this->withAvailableWorkers(['*' => 3]);

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        [$worksOn, $workers] = $picked[0];
        $this->assertEquals('*', $worksOn);
        $this->assertCount(3, $workers);
    }

    /**
     * @throws Exception
     */
    public function testFewWorkersWithSameSkill(): void
    {
        $callbackHasBeenCalled = false;
        $this->withAvailableWorkers(['send-emails' => 3]);

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        [$worksOn, $workers] = $picked[0];
        $this->assertEquals('send-emails', $worksOn);
        $this->assertCount(3, $workers);
    }

    /**
     * @throws Exception
     */
    public function testFewWorkersWithSomeDifferentSkills(): void
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

    /**
     * @throws Exception
     */
    public function testMoreWorkersThanAllowedPerUnit(): void
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
     * @param array<string, int> $workers
     *
     * @throws Exception
     */
    private function withAvailableWorkers(array $workers): void
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
            ->willReturn(new FakeCursor(array_values($workersThatShouldBeFound)))
        ;
    }

    private function withNoAvailableWorkers(): void
    {
        $this->repository
            ->expects($this->any())
            ->method('find')
            ->willReturn(new FakeCursor())
        ;
    }

    /**
     * @template T
     *
     * @param array<T> $expected
     * @param array<T> $given
     */
    private function assertArrayAreEquals(array $expected, array $given): void
    {
        sort($expected);
        sort($given);
        $this->assertEquals($expected, $given);
    }
}

/**
 * @implements \Iterator<int, array<string, mixed>>
 */
class FakeCursor implements CursorInterface, \Iterator
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $data;

    /**
     * @param array<int, array<string, mixed>> $data
     */
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

    /**
     * @param array<string, string> $typemap
     */
    public function setTypeMap(array $typemap): void
    {
        throw new \LogicException('Not implemented');
    }

    public function toArray(): never
    {
        throw new \LogicException('Not implemented');
    }

    /**
     * @return object|array<mixed>|null
     */
    public function current(): object|array|null
    {
        return current($this->data) !== false ? current($this->data) : null;
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
