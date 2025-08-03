<?php

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class ExitsAbruptly implements Workable
{
    use WorkableBehaviour;

    public function execute(): void
    {
        exit(-1);
    }
}
