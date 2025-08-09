<?php

namespace Recruiter\Worker;

readonly class Process implements ProcessInterface
{
    public static function withPid(int $pid): self
    {
        return new self($pid);
    }

    public function __construct(private int $pid)
    {
    }

    public function cleanUp(Repository $repository): void
    {
        if (!$this->isAlive()) {
            $repository->retireWorkerWithPid($this->pid);
        }
    }

    public function ifDead(): NullProcess|static
    {
        if ($this->isAlive()) {
            return new NullProcess();
        }

        return $this;
    }

    protected function isAlive(): bool
    {
        return posix_kill($this->pid, 0);
    }
}
