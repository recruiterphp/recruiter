<?php

namespace Recruiter;

use Recruiter\RetryPolicy\RetriableExceptionFilter;

trait RetryPolicyBehaviour
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(private readonly array $parameters = [])
    {
    }

    /**
     * @param class-string $retriableExceptionType
     */
    public function retryOnlyWhenExceptionIs(string $retriableExceptionType): RetriableExceptionFilter
    {
        return new RetriableExceptionFilter($this, [$retriableExceptionType]);
    }

    /**
     * @param class-string[] $retriableExceptionTypes
     */
    public function retryOnlyWhenExceptionsAre(array $retriableExceptionTypes): RetriableExceptionFilter
    {
        return new RetriableExceptionFilter($this, $retriableExceptionTypes);
    }

    public function schedule(JobAfterFailure $job): void
    {
        throw new \Exception('RetryPolicy::schedule(JobAfterFailure) need to be implemented');
    }

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return $this->parameters;
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new static($parameters);
    }
}
