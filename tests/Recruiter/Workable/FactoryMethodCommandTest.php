<?php

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
    public static function create()
    {
        return new self();
    }

    public function myObject()
    {
        return new DummyObject();
    }
}

class DummyObject
{
    public function myMethod($what, $value)
    {
        return $value;
    }

    public function myNeedyMethod(array $retryStatistics)
    {
        return $retryStatistics['retry_number'];
    }
}
