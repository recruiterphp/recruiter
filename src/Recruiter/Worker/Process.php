<?php

namespace Recruiter\Worker;

use Sink\BlackHole;

class Process
{
    public static function withPid(int $pid)
    {
        return new self($pid);
    }

    public function __construct(private readonly int $pid)
    {
    }

    public function cleanUp(Repository $repository): void
    {
        if (!$this->isAlive()) {
            $repository->retireWorkerWithPid($this->pid);
        }
    }

    public function ifDead(): BlackHole|static
    {
        if ($this->isAlive()) {
            return new BlackHole();
        }

        return $this;
    }

    protected function isAlive(): bool
    {
        return posix_kill($this->pid, 0);
    }
}
