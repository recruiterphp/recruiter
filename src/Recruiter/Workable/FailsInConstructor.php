<?php

declare(strict_types=1);

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class FailsInConstructor implements Workable
{
    use WorkableBehaviour;

    public function __construct(protected array $parameters = [], $fromRecruiter = true)
    {
        if ($fromRecruiter) {
            throw new \Exception('I am supposed to fail in constructor code for testing purpose');
        }
    }
}
