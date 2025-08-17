<?php

declare(strict_types=1);

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
    public function retryOnlyWhenExceptionIs(string $retriableExceptionType): RetryPolicy
    {
        return new RetriableExceptionFilter($this, [$retriableExceptionType]);
    }

    /**
     * @param class-string[] $retriableExceptionTypes
     */
    public function retryOnlyWhenExceptionsAre(array $retriableExceptionTypes): RetryPolicy
    {
        return new RetriableExceptionFilter($this, $retriableExceptionTypes);
    }

    public function schedule(JobAfterFailure $job): void
    {
        throw new \Exception('RetryPolicy::schedule(JobAfterFailure) need to be implemented');
    }

    public function export(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<mixed> $parameters
     */
    public static function import(array $parameters): static
    {
        return new static($parameters);
    }
}
