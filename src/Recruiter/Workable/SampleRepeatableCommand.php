<?php

namespace Recruiter\Workable;

use Recruiter\Repeatable;
use Recruiter\SchedulePolicy\EveryMinutes;
use Recruiter\SchedulePolicy;
use Recruiter\Workable;
use Recruiter\WorkableBehaviour;
use Recruiter\RepeatableBehaviour;

class SampleRepeatableCommand implements Workable, Repeatable
{
    use WorkableBehaviour, RepeatableBehaviour;

    public function execute()
    {
        var_export((new \DateTime())->format('c'));
    }

    public function urn(): string
    {
        return 'recruiter:sample:repeatable-job';
    }
}
