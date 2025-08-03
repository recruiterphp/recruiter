<?php

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class RecoverWorkableFromException implements Workable
{
    use WorkableBehaviour;

    public function __construct($parameters, protected $recoverForClass, protected $recoverForException)
    {
        $this->parameters = $parameters;
    }

    public function execute(): never
    {
        throw new \Exception('This job failed while instantiating a workable of class: ' . $this->recoverForClass . PHP_EOL . 'Original exception: ' . $this->recoverForException::class . PHP_EOL . $this->recoverForException->getMessage() . PHP_EOL . $this->recoverForException->getTraceAsString() . PHP_EOL);
    }

    public function getClass()
    {
        return $this->recoverForClass;
    }
}
