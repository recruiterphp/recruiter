<?php

namespace Recruiter\Workable;

use Recruiter\Repeatable;
use Recruiter\WorkableBehaviour;

class RecoverRepeatableFromException implements Repeatable
{
    use WorkableBehaviour;

    protected $recoverForClass;
    protected $recoverForException;

    public function __construct($parameters, $recoverForClass, $recoverForException)
    {
        $this->parameters = $parameters;
        $this->recoverForClass = $recoverForClass;
        $this->recoverForException = $recoverForException;
    }

    public function execute()
    {
        throw new \Exception('This job failed while instantiating a workable of class: ' . $this->recoverForClass . PHP_EOL . 'Original exception: ' . get_class($this->recoverForException) . PHP_EOL . $this->recoverForException->getMessage() . PHP_EOL . $this->recoverForException->getTraceAsString() . PHP_EOL);
    }

    public function getClass()
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
