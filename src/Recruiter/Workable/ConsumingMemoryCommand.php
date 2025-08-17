<?php

declare(strict_types=1);

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class ConsumingMemoryCommand implements Workable
{
    use WorkableBehaviour;

    public function execute(): void
    {
        if ($this->parameters['withMemoryLeak']) {
            global $occupied;
        }

        $occupied = new \SplFixedArray($this->parameters['howManyItems']);
    }
}
