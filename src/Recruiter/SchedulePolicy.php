<?php

namespace Recruiter;

use Timeless\Moment;

interface SchedulePolicy
{
    /**
     * Returns the next time the job is to be executed.
     */
    public function next(): Moment;

    /**
     * Export schedule policy parameters.
     *
     * @return array<string, mixed> An associative array of parameters that can be used to recreate the policy
     */
    public function export(): array;

    /**
     * Import schedule policy parameters.
     *
     * @param array<string, mixed> $parameters Previously exported parameters
     */
    public static function import(array $parameters): SchedulePolicy;
}
