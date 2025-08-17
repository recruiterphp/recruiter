<?php

declare(strict_types=1);

namespace Recruiter\Worker;

interface ProcessInterface
{
    public function cleanUp(Repository $repository): void;

    public function ifDead(): ProcessInterface;
}
