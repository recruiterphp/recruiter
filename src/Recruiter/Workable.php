<?php

namespace Recruiter;

interface Workable
{
    /**
     * Turn this `Recruiter\Workable` instance into a `Recruiter\Job` instance.
     */
    public function asJobOf(Recruiter $recruiter): JobToSchedule;

    /**
     * Export parameters that need to be persisted.
     *
     * @return array<mixed>
     */
    public function export(): array;

    /**
     * Import an array of parameters as a Workable instance.
     *
     * @param array<mixed> $parameters Previously exported parameters
     */
    public static function import(array $parameters): static;
}
