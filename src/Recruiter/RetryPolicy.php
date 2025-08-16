<?php

declare(strict_types=1);

namespace Recruiter;

interface RetryPolicy
{
    /**
     * Decide whether or not to reschedule a job. If you want to reschedule the
     * job use the appropriate methods on job or do nothing to if you don't
     * want to execute the job again.
     *
     * This method can
     * - schedule the job
     * - archive the job
     * - do nothing (and the job will be archived anyway)
     */
    public function schedule(JobAfterFailure $job): void;

    /**
     * Export retry policy parameters.
     */
    public function export(): array;

    /**
     * Import retry policy parameters.
     *
     * @param array $parameters Previously exported parameters
     */
    public static function import(array $parameters): RetryPolicy;

    /**
     * @return bool true if is the last retry
     */
    public function isLastRetry(Job $job): bool;
}
