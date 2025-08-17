<?php

declare(strict_types=1);

namespace Recruiter\Workable;

use PHPUnit\Framework\TestCase;

class FactoryMethodCommandTest extends TestCase
{
    public function testExecutedACommandReachableFromAStaticFactoryMethod(): void
    {
        $workable = FactoryMethodCommand::from('Recruiter\Workable\DummyFactory::create')
            ->myObject()
            ->myMethod('answer', 42)
        ;
        $this->assertEquals('42', $workable->execute());
    }

    public function testCanBeImportedAndExported(): void
    {
        $workable = FactoryMethodCommand::from('Recruiter\Workable\DummyFactory::create')
            ->myObject()
            ->myMethod('answer', 42)
        ;
        $this->assertEquals(
            $workable,
            FactoryMethodCommand::import($workable->export()),
        );
    }

    public function testPassesRetryStatisticsAsAnAdditionalArgumentToTheLastMethodToCall(): void
    {
        $workable = FactoryMethodCommand::from('Recruiter\Workable\DummyFactory::create')
            ->myObject()
            ->myNeedyMethod()
        ;
        $this->assertEquals(2, $workable->execute(['retry_number' => 2]));
    }
}

class DummyFactory
{
    public static function create(): self
    {
        return new self();
    }

    public function myObject(): DummyObject
    {
        return new DummyObject();
    }
}

class DummyObject
{
    public function myMethod(string $what, int $value): int
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
