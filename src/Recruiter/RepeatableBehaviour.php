<?php

namespace Recruiter;

trait RepeatableBehaviour
{
    public function asRepeatableJobOf(Recruiter $recruiter): Scheduler
    {
        return $recruiter
            ->repeatableJobOf($this)
            ->withUrn($this->urn())
            ->unique($this->unique())
        ;
    }
}
