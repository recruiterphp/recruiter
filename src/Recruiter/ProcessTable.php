<?php

declare(strict_types=1);

namespace Recruiter;

class ProcessTable
{
    public function isAlive($pid)
    {
        return posix_kill($pid, 0);
    }
}
