<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;

class RetriableException
{
    /** @var string */
    private $exceptionClass;

    /** @var RetryPolicy */
    private $retryPolicy;

    public function __construct(string $exceptionClass, RetryPolicy $retryPolicy)
    {
        if (!class_exists($exceptionClass)) {
            throw new \InvalidArgumentException("Class $exceptionClass doesn't exists");
        }
        if (!is_a($exceptionClass, \Throwable::class, $allowString = true)) {
            throw new \InvalidArgumentException("Class $exceptionClass is not Throwable");
        }
        $this->exceptionClass = $exceptionClass;
        $this->retryPolicy = $retryPolicy;
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
