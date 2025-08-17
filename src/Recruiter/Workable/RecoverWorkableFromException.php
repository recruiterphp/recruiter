<?php

declare(strict_types=1);

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class RecoverWorkableFromException implements Workable
{
    use WorkableBehaviour;

    /**
     * @param ?array<mixed> $parameters
     * @param ?class-string $recoverForClass
     */
    public function __construct(?array $parameters, protected ?string $recoverForClass, protected \Throwable $recoverForException)
    {
        $this->parameters = (array) $parameters;
    }

    public function execute(): never
    {
        throw new \Exception('This job failed while instantiating a workable of class: ' . $this->recoverForClass . PHP_EOL . 'Original exception: ' . $this->recoverForException::class . PHP_EOL . $this->recoverForException->getMessage() . PHP_EOL . $this->recoverForException->getTraceAsString() . PHP_EOL);
    }

    /**
     * @return ?class-string
     */
    public function getClass(): ?string
    {
        return $this->recoverForClass;
    }
}
