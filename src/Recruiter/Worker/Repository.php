<?php

declare(strict_types=1);

namespace Recruiter\Worker;

use MongoDB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime as MongoUTCDateTime;
use Recruiter\Worker;

class Repository
{
    private MongoDB\Collection $roster;

    public function __construct(MongoDB\Database $db)
    {
        $this->roster = $db->selectCollection('roster');
    }

    public function save(Worker $worker): void
    {
        $document = $worker->export();
        $this->roster->replaceOne(
            ['_id' => $document['_id']],
            $document,
            ['upsert' => true],
        );
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    public function atomicUpdate(Worker $worker, array $changeSet): void
    {
        $this->roster->updateOne(
            ['_id' => $worker->id()],
            ['$set' => $changeSet],
        );
    }

    public function refresh(Worker $worker): void
    {
        $updated = $this->roster->findOne(['_id' => $worker->id()]);
        assert(is_array($updated), new \LogicException("Document with _id {$worker->id()} is missing!"));
        $worker->updateWith($updated);
    }

    public function deadWorkers(\DateTimeImmutable $consideredDeadAt): MongoDB\Driver\CursorInterface
    {
        return $this->roster->find(
            ['last_seen_at' => [
                '$lt' => new MongoUTCDateTime($consideredDeadAt->format('U') * 1000)],
            ],
            ['projection' => ['_id' => true, 'assigned_to' => true]],
        );
    }

    public function retireWorkerWithIdIfNotAssigned(ObjectId $id): bool
    {
        $result = $this->roster->deleteOne(['_id' => $id, 'available' => true]);

        return $result->getDeletedCount() > 0;
    }

    public function retireWorkerWithId(ObjectId $id): void
    {
        $this->roster->deleteOne(['_id' => $id]);
    }

    public function retireWorkerWithPid(int $pid): void
    {
        $this->roster->deleteOne(['pid' => $pid]);
    }
}
