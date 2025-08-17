<?php

declare(strict_types=1);

namespace Recruiter;

use Recruiter\Exception\ImportException;

class SchedulePolicyInJob
{
    /**
     * @param array<string, mixed> $document
     *
     * @throws ImportException
     */
    public static function import(array $document): SchedulePolicy
    {
        if (!array_key_exists('schedule_policy', $document)) {
            throw new ImportException('Unable to import Job without data about SchedulePolicy object');
        }
        $dataAboutSchedulePolicyObject = $document['schedule_policy'];
        if (!array_key_exists('class', $dataAboutSchedulePolicyObject)) {
            throw new ImportException('Unable to import Job without a SchedulePolicy class');
        }
        if (!class_exists($dataAboutSchedulePolicyObject['class'])) {
            throw new ImportException('Unable to import Job with unknown SchedulePolicy class');
        }
        if (!method_exists($dataAboutSchedulePolicyObject['class'], 'import')) {
            throw new ImportException('Unable to import SchedulePolicy without method import');
        }

        return $dataAboutSchedulePolicyObject['class']::import($dataAboutSchedulePolicyObject['parameters']);
    }

    /**
     * @return array{
     *     schedule_policy: array{
     *         class: class-string,
     *         parameters: array<mixed>,
     *     },
     * }
     */
    public static function export(SchedulePolicy $schedulePolicy): array
    {
        return [
            'schedule_policy' => [
                'class' => $schedulePolicy::class,
                'parameters' => $schedulePolicy->export(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialize(): array
    {
        return [];
    }
}
