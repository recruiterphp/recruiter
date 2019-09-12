<?php
namespace Recruiter\Scheduler;

use Exception;
use MongoDB;
use Recruiter\Job;
use Recruiter\JobToSchedule;
use Recruiter\Recruiter;
use Recruiter\Scheduler;
use RuntimeException;
use Timeless as T;

class Repository
{
    private $schedulers;

    public function __construct(MongoDB\Database $db)
    {
        $this->schedulers = $db->selectCollection('schedulers');
    }

    public function all()
    {
        return $this->map(
            $this->schedulers->find([], [
                'sort' => ['scheduled_at' => -1],
            ])
        );
    }

    public function save(Scheduler $scheduler)
    {
        $document = $scheduler->export();
        $this->schedulers->replaceOne(
            ['_id' => $document['_id']],
            $document,
            ['upsert' => true]
        );
    }

    public function create(Scheduler $scheduler)
    {
        $document = $scheduler->export();

        if (0 === $this->schedulers->count(['urn' => $document['urn']])) {
            $this->schedulers->insertOne($document);
        } else {
            $document = array_filter($document, function ($key) {
                return in_array($key, [
                    'job',
                    'schedule_policy',
                    'unique',
                ]);
            }, ARRAY_FILTER_USE_KEY);

            $this->schedulers->updateOne(
                ['urn' => $scheduler->urn()],
                ['$set' => $document]
            );
        }
    }

    public function deleteByUrn(string $urn)
    {
        $this->schedulers->deleteOne(['urn' => $urn]);
    }

    private function map($cursor)
    {
        $schedulers = [];
        foreach ($cursor as $document) {
            $schedulers[] = Scheduler::import($document, $this);
        }

        return $schedulers;
    }
}
