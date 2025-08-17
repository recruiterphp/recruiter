<?php

declare(strict_types=1);

namespace Recruiter\SchedulePolicy;

use Recruiter\SchedulePolicy;
use Timeless\Moment;

class EveryMinutes implements SchedulePolicy
{
    public function __construct()
    {
    }

    public function next(): Moment
    {
        $timestamp = mktime(intval(date('H')), intval(date('i')) + 1, 0);
        assert(false !== $timestamp);

        return Moment::fromTimestamp($timestamp);
    }

    public function export(): array
    {
        return [];
    }

    public static function import(array $parameters): SchedulePolicy
    {
        return new self();
    }
}
