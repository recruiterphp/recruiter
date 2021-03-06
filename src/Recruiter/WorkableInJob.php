<?php
namespace Recruiter;

use Exception;
use Recruiter\Workable\RecoverWorkableFromException;
use Throwable;

class WorkableInJob
{
    // TODO: resolve the duplication with RepeatableInJob
    public static function import($document): Workable
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
            $workable = $dataAboutWorkableObject['class']::import($dataAboutWorkableObject['parameters']);
            assert($workable instanceof Workable);
            return $workable;

        } catch (Throwable $e) {
            return new RecoverWorkableFromException($dataAboutWorkableObject['parameters'], $dataAboutWorkableObject['class'], $e);
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
