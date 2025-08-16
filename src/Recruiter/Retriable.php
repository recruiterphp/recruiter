<?php

declare(strict_types=1);

namespace Recruiter;

interface Retriable
{
    /**
     * Declare what instance of `Recruiter\RetryPolicy` should be used for a `Recruiter\Workable`.
     */
    public function retryWithPolicy(): RetryPolicy;
}
