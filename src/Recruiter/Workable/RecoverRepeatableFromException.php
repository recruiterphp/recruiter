<?php

namespace Recruiter\Workable;

use Recruiter\Repeatable;
use Recruiter\WorkableBehaviour;

class RecoverRepeatableFromException implements Repeatable
{
    use WorkableBehaviour;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(array $parameters, protected string $recoverForClass, protected \Throwable $recoverForException)
    {
        $this->parameters = $parameters;
    }

    public function execute(): never
    {
        throw new \Exception('This job failed while instantiating a workable of class: ' . $this->recoverForClass . PHP_EOL . 'Original exception: ' . $this->recoverForException::class . PHP_EOL . $this->recoverForException->getMessage() . PHP_EOL . $this->recoverForException->getTraceAsString() . PHP_EOL);
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->recoverForClass;
    }

    public function urn(): string
    {
        $recoverForInstance = new $this->recoverForClass($this->parameters);
        assert($recoverForInstance instanceof Repeatable);

        return $recoverForInstance->urn();
    }

    public function unique(): bool
    {
        $recoverForInstance = new $this->recoverForClass($this->parameters);
        assert($recoverForInstance instanceof Repeatable);

        return $recoverForInstance->unique();
    }
}
