<?php

namespace Recruiter\Workable;

use Recruiter\Repeatable;
use Recruiter\RepeatableBehaviour;
use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class SampleRepeatableCommand implements Workable, Repeatable
{
    use WorkableBehaviour;
    use RepeatableBehaviour;

    public function execute()
    {
        var_export((new \DateTime())->format('c'));
    }

    public function urn(): string
    {
        return 'recruiter:sample:repeatable-job';
    }

    public function unique(): bool
    {
        return false;
    }
}
