<?php

declare(strict_types=1);

namespace Recruiter;

use Recruiter\Job\Repository;
use Timeless as T;
use Timeless\Interval;

class Cleaner
{
    public function __construct(private readonly Repository $repository)
    {
    }

    public function cleanArchived(Interval $gracePeriod)
    {
        $upperLimit = T\now()->before($gracePeriod);

        return $this->repository->cleanArchived($upperLimit);
    }

    public function cleanScheduled(?Interval $gracePeriod = null)
    {
        $upperLimit = T\now();
        if (!is_null($gracePeriod)) {
            $upperLimit = $upperLimit->before($gracePeriod);
        }

        return $this->repository->cleanScheduled($upperLimit);
    }

    public function bye()
    {
    }
}
