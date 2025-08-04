<?php

namespace Recruiter\SchedulePolicy;

use Cron\CronExpression;
use Recruiter\SchedulePolicy;
use Timeless\Moment;

class Cron implements SchedulePolicy
{
    public function __construct(private readonly string $cronExpression, private readonly ?\DateTime $now = null)
    {
    }

    public function next(): Moment
    {
        return Moment::fromDateTime(
            CronExpression::factory($this->cronExpression)->getNextRunDate($this->now ?? 'now'),
        );
    }

    public function export(): array
    {
        return [
            'cron_expression' => $this->cronExpression,
            'now' => $this->now ? $this->now->getTimestamp() : null,
        ];
    }

    public static function import(array $parameters): SchedulePolicy
    {
        $now = null;
        if (isset($parameters['now'])) {
            $now = \DateTime::createFromFormat('U', $parameters['now']);
            $now = false === $now ? null : $now;
        }

        return new self($parameters['cron_expression'], $now);
    }
}
