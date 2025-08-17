<?php

declare(strict_types=1);

namespace Recruiter;

use Recruiter\Exception\ImportException;

interface Workable
{
    /**
     * Turn this `Recruiter\Workable` instance into a `Recruiter\Job` instance.
     */
    public function asJobOf(Recruiter $recruiter): JobToSchedule;

    /**
     * Export parameters that need to be persisted.
     */
    public function export(): array;

    /**
     * Import an array of parameters as a Workable instance.
     *
     * @param array $parameters Previously exported parameters
     *
     * @throws ImportException
     */
    public static function import(array $parameters): static;
}
