<?php

namespace Recruiter;

trait WorkableBehaviour
{
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

    public function export(): array
    {
        return $this->parameters;
    }

    public static function import(array $parameters): static
    {
        return new static($parameters);
    }
}
