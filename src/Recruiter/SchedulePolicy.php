<?php

namespace Recruiter;

use Timeless\Moment;

interface SchedulePolicy
{
    /**
     * Returns the next time the job is to be executed
     *
     * @return Moment
     */
    public function next(): Moment;

    /**
     * Export schedule policy parameters
     *
     * @return array
     */
    public function export(): array;

    /**
     * Import schedule policy parameters
     *
     * @param array $parameters Previously exported parameters
     *
     * @return SchedulePolicy
     */
    public static function import(array $parameters): SchedulePolicy;
}
