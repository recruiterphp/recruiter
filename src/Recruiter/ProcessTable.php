<?php

declare(strict_types=1);

namespace Recruiter;

class ProcessTable
{
    public function isAlive(int $pid): bool
    {
        return posix_kill($pid, 0);
    }
}
