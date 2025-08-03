<?php

namespace Recruiter;

class SchedulePolicyInJob
{
    public static function import($document): SchedulePolicy
    {
        if (!array_key_exists('schedule_policy', $document)) {
            throw new \Exception('Unable to import Job without data about SchedulePolicy object');
        }
        $dataAboutSchedulePolicyObject = $document['schedule_policy'];
        if (!array_key_exists('class', $dataAboutSchedulePolicyObject)) {
            throw new \Exception('Unable to import Job without a SchedulePolicy class');
        }
        if (!class_exists($dataAboutSchedulePolicyObject['class'])) {
            throw new \Exception('Unable to import Job with unknown SchedulePolicy class');
        }
        if (!method_exists($dataAboutSchedulePolicyObject['class'], 'import')) {
            throw new \Exception('Unable to import SchedulePolicy without method import');
        }

        return $dataAboutSchedulePolicyObject['class']::import($dataAboutSchedulePolicyObject['parameters']);
    }

    public static function export($schedulePolicy)
    {
        return [
            'schedule_policy' => [
                'class' => get_class($schedulePolicy),
                'parameters' => $schedulePolicy->export(),
            ],
        ];
    }

    public static function initialize()
    {
        return [];
    }
}
