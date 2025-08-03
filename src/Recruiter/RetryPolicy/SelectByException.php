<?php

namespace Recruiter\RetryPolicy;

use Exception;
use Recruiter\Job;
use Recruiter\JobAfterFailure;
use Recruiter\RetryPolicy;

/**
 * Select retry policies based on the raised exception.
 *
 * If a job fails with an exception it's possible to select a retry
 * policy instance based on the class of the exception. The exception
 * list is ordered so if you want to define a default retry policy at
 * the end it's possible use Exception::class.
 *
 *     ->retryWith(
 *         SelectByException::create()
 *             ->when(AnException::class, RetryForever::afterSeconds(10))
 *             ->when(AnotherException::class, RetryManyTimes::forTimes(3, 60))
 *             ->build()
 *       )
 */
class SelectByException implements RetryPolicy
{
    /**
     * @var array<RetriableException>
     */
    private $exceptions;

    public static function create(): SelectByExceptionBuilder
    {
        return new SelectByExceptionBuilder();
    }

    public function __construct(array $exceptions)
    {
        $this->exceptions = $exceptions;
    }

    public function schedule(JobAfterFailure $job)
    {
        $exception = $job->causeOfFailure();
        if ($this->isRetriable($exception)) {
            $this->retryPolicyFor($exception)->schedule($job);
        } else {
            $job->archive('non-retriable-exception');
        }
    }

    public function export(): array
    {
        return array_map(
            function (RetriableException $retriableException) {
                $retryPolicy = $retriableException->retryPolicy();

                return [
                    'when' => $retriableException->exceptionClass(),
                    'then' => [
                        'class' => get_class($retryPolicy),
                        'parameters' => $retryPolicy->export(),
                    ],
                ];
            },
            $this->exceptions,
        );
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new self(
            array_reduce(
                $parameters,
                function ($exceptions, $parameters) {
                    $exceptionClass = $parameters['when'];
                    $retryPolicyClass = $parameters['then']['class'];
                    $retryPolicyParameters = $parameters['then']['parameters'];
                    $exceptions[] = new RetriableException($exceptionClass, $retryPolicyClass::import($retryPolicyParameters));

                    return $exceptions;
                },
            ),
        );
    }

    public function isLastRetry(Job $job): bool
    {
        // I cannot answer to that so... true only if everybody says true
        return array_all(
            $this->exceptions,
            function (RetriableException $retriableException) use ($job) {
                return $retriableException->retryPolicy()->isLastRetry($job);
            },
        );
    }

    private function isRetriable($exception): bool
    {
        try {
            $this->retryPolicyFor($exception);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function retryPolicyFor(?object $exception): RetryPolicy
    {
        if (!is_null($exception) && is_object($exception)) {
            /** @var RetriableException $retriableException */
            foreach ($this->exceptions as $retriableException) {
                $exceptionClass = $retriableException->exceptionClass();
                if ($exception instanceof $exceptionClass) {
                    return $retriableException->retryPolicy();
                }
            }
            if ($exception instanceof \Throwable) {
                throw new \Exception('Unable to find a RetryPolicy associated to exception: ' . get_class($exception), 0, $exception);
            }
        }
        throw new \Exception('Unable to find a RetryPolicy associated to: ' . var_export($exception, true));
    }
}
