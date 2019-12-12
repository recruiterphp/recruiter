<?php
namespace Recruiter;

use Exception;
use Recruiter\Workable\RecoverRepeatableFromException;

class RepeatableInJob
{
    // TODO: resolve duplication with WorkableInJob
    public static function import($document): Repeatable
    {
        $dataAboutWorkableObject = [
            'parameters' => null,
            'class' => null,
        ];

        try {
            if (!array_key_exists('workable', $document)) {
                throw new Exception('Unable to import Job without data about Workable object');
            }
            $dataAboutWorkableObject = $document['workable'];
            if (!array_key_exists('class', $dataAboutWorkableObject)) {
                throw new Exception('Unable to import Job without a class');
            }
            if (!class_exists($dataAboutWorkableObject['class'])) {
                throw new Exception('Unable to import Job with unknown Workable class');
            }
            if (!method_exists($dataAboutWorkableObject['class'], 'import')) {
                throw new Exception('Unable to import Workable without method import');
            }
            $repeatable =  $dataAboutWorkableObject['class']::import($dataAboutWorkableObject['parameters']);
            assert($repeatable instanceof Repeatable);
            return $repeatable;

        } catch (Exception $e) {
            return new RecoverRepeatableFromException($dataAboutWorkableObject['parameters'], $dataAboutWorkableObject['class'], $e);
        }
    }

    public static function export($workable, $methodToCall)
    {
        return [
            'workable' => [
                'class' => self::classNameOf($workable),
                'parameters' => $workable->export(),
                'method' => $methodToCall,
            ]
        ];
    }

    public static function initialize()
    {
        return ['workable' => ['method' => 'execute']];
    }

    private static function classNameOf($workable)
    {
        $workableClassName = get_class($workable);
        if (method_exists($workable, 'getClass')) {
            $workableClassName = $workable->getClass();
        }
        return $workableClassName;
    }
}
