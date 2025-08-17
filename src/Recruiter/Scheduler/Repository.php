<?php

declare(strict_types=1);

namespace Recruiter\Scheduler;

use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\CursorInterface;
use Recruiter\Scheduler;

class Repository
{
    private Collection $schedulers;

    public function __construct(Database $db)
    {
        $this->schedulers = $db->selectCollection('schedulers');
    }

    /**
     * @return Scheduler[]
     */
    public function all(): array
    {
        return $this->map(
            $this->schedulers->find([], [
                'sort' => ['scheduled_at' => -1],
            ]),
        );
    }

    public function save(Scheduler $scheduler): void
    {
        $document = $scheduler->export();
        $this->schedulers->replaceOne(
            ['_id' => $document['_id']],
            $document,
            ['upsert' => true],
        );
    }

    public function create(Scheduler $scheduler): void
    {
        $document = $scheduler->export();

        if (0 === $this->schedulers->count(['urn' => $document['urn']])) {
            $this->schedulers->insertOne($document);
        } else {
            $document = array_filter($document, fn ($key) => in_array($key, [
                'job',
                'schedule_policy',
                'unique',
            ]), ARRAY_FILTER_USE_KEY);

            $this->schedulers->updateOne(
                ['urn' => $scheduler->urn()],
                ['$set' => $document],
            );
        }
    }

    public function deleteByUrn(string $urn): void
    {
        $this->schedulers->deleteOne(['urn' => $urn]);
    }

    /**
     * @return Scheduler[]
     */
    private function map(CursorInterface $cursor): array
    {
        $schedulers = [];
        foreach ($cursor as $document) {
            $schedulers[] = Scheduler::import($document, $this);
        }

        return $schedulers;
    }
}
