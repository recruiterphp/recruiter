<?php

declare(strict_types=1);

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;

class DoNotDoItAgain implements RetryPolicy
{
    use RetryPolicyBehaviour;

    public function schedule(JobAfterFailure $job): void
    {
        // doing nothing means to avoid to reschedule the job
    }

    public function isLastRetry(Job $job): bool
    {
        return true;
    }
}
