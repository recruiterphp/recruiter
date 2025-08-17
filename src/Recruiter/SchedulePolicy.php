<?php

declare(strict_types=1);

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
     * @return array<mixed>
     */
    public function export(): array;

    /**
     * Import schedule policy parameters.
     *
     * @param array<mixed> $parameters Previously exported parameters
     */
    public static function import(array $parameters): SchedulePolicy;
}
