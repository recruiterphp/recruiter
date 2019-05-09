<?php

namespace Recruiter;

interface Repeatable
{
    /**
     * Assign an unique name to a workable in order to handle idempotency,
     * only one job with the same urn can be queued
     *
     * @return SchedulePolicy
     */
    public function urn(): string;
}
