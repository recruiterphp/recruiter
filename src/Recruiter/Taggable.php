<?php

declare(strict_types=1);

namespace Recruiter;

interface Taggable
{
    /**
     * A Job can decide its own tags. Tags are useful to correlate jobs.
     *
     * @return array<string> Strings to be used to tag the job
     */
    public function taggedAs(): array;
}
