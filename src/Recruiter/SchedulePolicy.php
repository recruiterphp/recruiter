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
     */
    public function export(): array;

    /**
     * Import schedule policy parameters.
     *
     * @param array $parameters Previously exported parameters
     */
    public static function import(array $parameters): SchedulePolicy;
}
