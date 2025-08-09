<?php

namespace Recruiter;

use Recruiter\Workable\RecoverRepeatableFromException;

class RepeatableInJob
{
    // TODO: resolve duplication with WorkableInJob
    /**
     * @param array<mixed> $document ,
     */
    public static function import(array $document): Repeatable
    {
        $dataAboutWorkableObject = [
            'parameters' => null,
            'class' => null,
        ];

        try {
            if (!array_key_exists('workable', $document)) {
                throw new \InvalidArgumentException('Unable to import Job without data about Workable object');
            }
            $dataAboutWorkableObject = $document['workable'];
            if (!array_key_exists('class', $dataAboutWorkableObject)) {
                throw new \InvalidArgumentException('Unable to import Job without a class');
            }
            if (!class_exists($dataAboutWorkableObject['class'])) {
                throw new \InvalidArgumentException('Unable to import Job with unknown Workable class');
            }
            if (!method_exists($dataAboutWorkableObject['class'], 'import')) {
                throw new \InvalidArgumentException('Unable to import Workable without method import');
            }

            $repeatable = $dataAboutWorkableObject['class']::import($dataAboutWorkableObject['parameters']);
            assert($repeatable instanceof Repeatable);

            return $repeatable;
        } catch (\Exception $e) {
            return new RecoverRepeatableFromException($dataAboutWorkableObject['parameters'], $dataAboutWorkableObject['class'], $e);
        }
    }

    /**
     * @return array{
     *     workable: array{
     *         class: class-string<Repeatable>,
     *         parameters: array<mixed>,
     *         method: string
     *     }
     * }
     */
    public static function export(Repeatable $repeatable, string $methodToCall): array
    {
        return [
            'workable' => [
                'class' => self::classNameOf($repeatable),
                'parameters' => $repeatable->export(),
                'method' => $methodToCall,
            ],
        ];
    }

    /**
     * @return array{workable: array{method: string}}
     */
    public static function initialize(): array
    {
        return ['workable' => ['method' => 'execute']];
    }

    /**
     * @return class-string<Repeatable>
     */
    private static function classNameOf(Repeatable $repeatable): string
    {
        $repeatableClassName = $repeatable::class;
        if (method_exists($repeatable, 'getClass')) {
            $repeatableClassName = $repeatable->getClass();
        }

        return $repeatableClassName;
    }
}
