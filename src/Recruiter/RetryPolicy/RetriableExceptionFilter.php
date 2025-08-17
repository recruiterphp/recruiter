<?php

declare(strict_types=1);

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;

readonly class RetriableExceptionFilter implements RetryPolicy
{
    /**
     * @param class-string $exceptionClass fully qualified class or interface name
     *
     * @return self
     */
    public static function onlyFor(string $exceptionClass, RetryPolicy $retryPolicy)
    {
        return new self($retryPolicy, [$exceptionClass]);
    }

    /**
     * @param class-string[] $retriableExceptions
     */
    public function __construct(private RetryPolicy $filteredRetryPolicy, private array $retriableExceptions = ['Exception'])
    {
        $this->ensureAreAllExceptions($retriableExceptions);
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
     *     retriable_exceptions: class-string[],
     *     filtered_retry_policy: array{
     *         class: class-string,
     *         parameters: array<mixed>,
     *     },
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

    /**
     * @param array{
     *     retriable_exceptions: class-string[],
     *     filtered_retry_policy: array{
     *         class: class-string,
     *         parameters: array<mixed>,
     *     },
     * } $parameters
     */
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
     * @param class-string[] $exceptions
     */
    private function ensureAreAllExceptions(array $exceptions): void
    {
        foreach ($exceptions as $exception) {
            if (!is_a($exception, 'Throwable', true)) {
                throw new \InvalidArgumentException("Only subclasses of Exception can be retriable exceptions, '{$exception}' is not");
            }
        }
    }

    private function isExceptionRetriable(?\Throwable $exception): bool
    {
        if (null == $exception) {
            return false;
        }

        return array_any(
            $this->retriableExceptions,
            fn ($retriableExceptionType) => $exception instanceof $retriableExceptionType,
        );
    }
}
