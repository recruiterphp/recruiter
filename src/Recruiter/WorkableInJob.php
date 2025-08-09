<?php

namespace Recruiter;

use Recruiter\Workable\RecoverWorkableFromException;

class WorkableInJob
{
    // TODO: resolve the duplication with RepeatableInJob
    /**
     * @param array<string, mixed> $document
     */
    public static function import(array $document): Workable
    {
        $dataAboutWorkableObject = [
            'parameters' => null,
            'class' => null,
        ];

        try {
            if (!array_key_exists('workable', $document)) {
                throw new \Exception('Unable to import Job without data about Workable object');
            }
            $dataAboutWorkableObject = $document['workable'];
            if (!array_key_exists('class', $dataAboutWorkableObject)) {
                throw new \Exception('Unable to import Job without a class');
            }
            if (!class_exists($dataAboutWorkableObject['class'])) {
                throw new \Exception('Unable to import Job with unknown Workable class');
            }
            if (!method_exists($dataAboutWorkableObject['class'], 'import')) {
                throw new \Exception('Unable to import Workable without method import');
            }
            $workable = $dataAboutWorkableObject['class']::import($dataAboutWorkableObject['parameters']);
            assert($workable instanceof Workable);

            return $workable;
        } catch (\Throwable $e) {
            return new RecoverWorkableFromException($dataAboutWorkableObject['parameters'], $dataAboutWorkableObject['class'], $e);
        }
    }

    /**
     * @return array<string, array<string, string | array<mixed>>>
     */
    public static function export(Workable $workable, string $methodToCall): array
    {
        return [
            'workable' => [
                'class' => self::classNameOf($workable),
                'parameters' => $workable->export(),
                'method' => $methodToCall,
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function initialize(): array
    {
        return ['workable' => ['method' => 'execute']];
    }

    /**
     * @return class-string<Workable>
     */
    private static function classNameOf(Workable $workable): string
    {
        $workableClassName = $workable::class;
        if (method_exists($workable, 'getClass')) {
            $workableClassName = $workable->getClass();
        }

        return $workableClassName;
    }
}
