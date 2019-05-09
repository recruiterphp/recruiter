<?php

namespace Recruiter;

trait RepeatableBehaviour
{
    public function asRepeatableJobOf(Recruiter $recruiter)
    {
        return $recruiter->repeatableJobOf($this);
    }
}
