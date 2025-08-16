<?php

declare(strict_types=1);

namespace Recruiter;

trait RepeatableBehaviour
{
    public function asRepeatableJobOf(Recruiter $recruiter)
    {
        return $recruiter
            ->repeatableJobOf($this)
            ->withUrn($this->urn())
            ->unique($this->unique())
        ;
    }
}
