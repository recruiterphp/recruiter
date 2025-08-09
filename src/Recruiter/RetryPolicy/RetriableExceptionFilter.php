<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;

class RetriableExceptionFilter implements RetryPolicy
{
    /**
     * @var array<class-string<\Throwable>>
     */
    private array $retriableExceptions;

    /**
     * @param class-string $exceptionClass fully qualified class or interface name
     */
    public static function onlyFor(string $exceptionClass, RetryPolicy $retryPolicy): self
    {
        return new self($retryPolicy, [$exceptionClass]);
    }

    /**
     * @param array<class-string> $retriableExceptions
     */
    public function __construct(private readonly RetryPolicy $filteredRetryPolicy, array $retriableExceptions = ['Exception'])
    {
        $this->retriableExceptions = $this->ensureAreAllExceptions($retriableExceptions);
    }

    public function schedule(JobAfterFailure $job): void
    {
        if ($this->isExceptionRetriable($job->causeOfFailure())) {
            $this->filteredRetryPolicy->schedule($job);
        } else {
            $job->archive('non-retriable-exception');
        }
    }

    /**
     * @return array{
     *     retriable_exceptions: array<class-string>,
     *     filtered_retry_policy: array{class: class-string, parameters: array<string, mixed>}
     * }
     */
    public function export(): array
    {
        return [
            'retriable_exceptions' => $this->retriableExceptions,
            'filtered_retry_policy' => [
                'class' => $this->filteredRetryPolicy::class,
                'parameters' => $this->filteredRetryPolicy->export(),
            ],
        ];
    }

    public static function import(array $parameters): RetryPolicy
    {
        $filteredRetryPolicy = $parameters['filtered_retry_policy'];
        $retriableExceptions = $parameters['retriable_exceptions'];

        return new self(
            $filteredRetryPolicy['class']::import($filteredRetryPolicy['parameters']),
            $retriableExceptions,
        );
    }

    public function isLastRetry(Job $job): bool
    {
        return $this->filteredRetryPolicy->isLastRetry($job);
    }

    /**
     * @param array<class-string> $exceptions
     *
     * @return array<class-string<\Throwable>>
     *
     * @throws \InvalidArgumentException
     */
    private function ensureAreAllExceptions(array $exceptions): array
    {
        foreach ($exceptions as $exception) {
            if (!is_a($exception, 'Throwable', true)) {
                throw new \InvalidArgumentException("Only subclasses of Exception can be retriable exceptions, '{$exception}' is not");
            }
        }

        return $exceptions;
    }

    private function isExceptionRetriable(?\Throwable $exception): bool
    {
        return array_any(
            $this->retriableExceptions,
            fn ($retriableExceptionType): bool => $exception instanceof $retriableExceptionType,
        );
    }
}
