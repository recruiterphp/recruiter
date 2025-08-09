<?php

namespace Recruiter\Worker;

class NullProcess implements ProcessInterface
{
    public function cleanUp(Repository $repository): void
    {
        // do nothing on purpose
    }

    public function ifDead(): static
    {
        return $this;
    }
}
