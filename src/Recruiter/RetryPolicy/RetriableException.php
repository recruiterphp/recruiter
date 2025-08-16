<?php

declare(strict_types=1);

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;

class RetriableException
{
    /** @var string */
    private $exceptionClass;

    public function __construct(string $exceptionClass, private readonly RetryPolicy $retryPolicy)
    {
        if (!class_exists($exceptionClass)) {
            throw new \InvalidArgumentException("Class $exceptionClass doesn't exists");
        }
        if (!is_a($exceptionClass, \Throwable::class, $allowString = true)) {
            throw new \InvalidArgumentException("Class $exceptionClass is not Throwable");
        }
        $this->exceptionClass = $exceptionClass;
    }

    public function exceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function retryPolicy(): RetryPolicy
    {
        return $this->retryPolicy;
    }
}
