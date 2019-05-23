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

        if ($scheduler->urn()) {
            $filter = ['urn' => $document['urn']];
            unset($document['_id']);
        } else {
            $filter = ['_id' => $document['_id']];
        }

        $this->schedulers->replaceOne(
            $filter,
            $document,
            ['upsert' => true]
        );
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
