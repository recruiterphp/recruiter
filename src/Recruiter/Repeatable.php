<?php

namespace Recruiter;

interface Repeatable
{
    /**
     * Assign an unique name to the scheduler in order to handle idempotency,
     * only one scheduler with the same urn can be exists
     *
     * @return string
     */
    public function urn(): string;

    /**
     * A scheduler can be schedule a job while another is still in the queue
     * (i.e. when a job still running while a new scheduling time is passed)
     * This method determines wheter can happen or not:
     *
     * true: only one job at a time can be queued
     * false: there may be more concurrent jobs at a time
     *
     * @return boolean
     */
    public function unique(): bool;
}
