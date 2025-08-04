<?php

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
        return Moment::fromTimestamp(mktime(intval(date('H')), intval(date('i')) + 1, 0));
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
