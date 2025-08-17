<?php

declare(strict_types=1);

namespace Recruiter;

use Recruiter\Exception\ImportException;
use Recruiter\Workable\RecoverWorkableFromException;

class WorkableInJob
{
    // TODO: resolve the duplication with RepeatableInJob
    /**
     * @param array{workable?: array{
     *     method?: string,
     *     class?: class-string,
     *     parameters?: array<mixed>,
     * }} $document
     *
     * @throws ImportException
     */
    public static function import(array $document): Workable
    {
        try {
            if (!array_key_exists('workable', $document)) {
                throw new ImportException('Unable to import Job without data about Workable object');
            }
            $dataAboutWorkableObject = $document['workable'];
            if (!array_key_exists('class', $dataAboutWorkableObject)) {
                throw new ImportException('Unable to import Job without a class');
            }
            if (!class_exists($dataAboutWorkableObject['class'])) {
                throw new ImportException('Unable to import Job with unknown Workable class');
            }
            if (!method_exists($dataAboutWorkableObject['class'], 'import')) {
                throw new ImportException('Unable to import Workable without method import');
            }
            $workable = $dataAboutWorkableObject['class']::import($dataAboutWorkableObject['parameters']);
            assert($workable instanceof Workable);

            return $workable;
        } catch (\Throwable $e) {
            return new RecoverWorkableFromException($dataAboutWorkableObject['parameters'] ?? null, $dataAboutWorkableObject['class'] ?? null, $e);
        }
    }

    /**
     * @return array{
     *     workable: array{
     *         class: class-string,
     *         parameters: array<mixed>,
     *         method: string,
     *     }
     * }
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
     * @return array{
     *     workable: array{
     *         method: string,
     *     }
     * }
     */
    public static function initialize(): array
    {
        return ['workable' => ['method' => 'execute']];
    }

    /**
     * @return class-string
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
