<?php

declare(strict_types=1);

namespace Recruiter;

use MongoDB\Database;
use PHPUnit\Framework\TestCase;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;

class FactoryTest extends TestCase
{
    private Factory $factory;
    private MongoURI $mongoURI;

    protected function setUp(): void
    {
        $this->factory = new Factory();
        $this->mongoURI = MongoURI::fromEnvironment();
    }

    public function testShouldCreateAMongoDatabaseConnection(): void
    {
        $this->assertInstanceOf(
            Database::class,
            $this->creationOfDefaultMongoDb(),
        );
    }

    public function testWriteConcernIsMajorityByDefault(): void
    {
        $mongoDb = $this->creationOfDefaultMongoDb();
        $this->assertEquals('majority', $mongoDb->getWriteConcern()->getW());
    }

    public function testShouldOverwriteTheWriteConcernPassedInTheOptions(): void
    {
        $mongoDb = $this->factory->getMongoDb(
            $this->mongoURI,
            [
                'connectTimeoutMS' => 1000,
                'w' => '0',
            ],
        );

        $this->assertEquals('majority', $mongoDb->getWriteConcern()->getW());
    }

    private function creationOfDefaultMongoDb(): Database
    {
        return $this->factory->getMongoDb(
            $this->mongoURI,
            [
                'connectTimeoutMS' => 1000,
                'w' => '0',
            ],
        );
    }
}
