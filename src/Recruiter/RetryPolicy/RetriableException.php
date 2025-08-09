<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;

readonly class RetriableException
{
    /**
     * @param class-string $exceptionClass
     */
    public function __construct(private string $exceptionClass, private RetryPolicy $retryPolicy)
    {
        if (!class_exists($exceptionClass)) {
            throw new \InvalidArgumentException("Class $exceptionClass doesn't exists");
        }
        if (!is_a($exceptionClass, \Throwable::class, $allowString = true)) {
            throw new \InvalidArgumentException("Class $exceptionClass is not Throwable");
        }
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
