<?php

namespace Recruiter;

trait WorkableBehaviour
{
    /**
     * @param array<string, mixed> $parameters
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
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function import(array $parameters): static
    {
        return new static($parameters);
    }
}
