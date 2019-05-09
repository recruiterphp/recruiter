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

    public function save(Scheduler $job)
    {
        $document = $job->export();
        $this->schedulers->replaceOne(
            ['_id' => $document['_id']],
            $document,
            ['upsert' => true]
        );
    }

    private function map($cursor)
    {
        $jobs = [];
        foreach ($cursor as $document) {
            $jobs[] = Scheduler::import($document, $this);
        }

        return $jobs;
    }
}
