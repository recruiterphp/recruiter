<?php

declare(strict_types=1);

namespace Recruiter;

use Recruiter\Exception\ImportException;

trait WorkableBehaviour
{
    /**
     * @param array<mixed> $parameters
     */
    final public function __construct(protected array $parameters = [])
    {
    }

    public function asJobOf(Recruiter $recruiter): JobToSchedule
    {
        return $recruiter->jobOf($this);
    }

    public function execute(): never
    {
        throw new \Exception('Workable::execute() need to be implemented');
    }

    /**
     * @return array<mixed>
     */
    public function export(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<mixed> $parameters
     *
     * @throws ImportException
     */
    public static function import(array $parameters): static
    {
        return new static($parameters);
    }
}
