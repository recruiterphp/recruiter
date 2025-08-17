<?php

declare(strict_types=1);

namespace Recruiter\Worker;

class NullProcess implements ProcessInterface
{
    public function cleanUp(Repository $repository): void
    {
        // Do nothing - process is already dead
    }

    public function ifDead(): ProcessInterface
    {
        return $this;
    }
}
