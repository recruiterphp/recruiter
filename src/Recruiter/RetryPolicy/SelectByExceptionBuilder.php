<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;
use Symfony\Component\Console\Exception\LogicException;

class SelectByExceptionBuilder
{
    /**
     * @var array<RetriableException>
     */
    private array $exceptions = [];

    /**
     * @var class-string<\Throwable>|null
     */
    private ?string $currentException = null;

    public function __construct()
    {
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     *
     * @return $this
     */
    public function when(string $exceptionClass): self
    {
        $this->currentException = $exceptionClass;

        return $this;
    }

    public function then(RetryPolicy $retryPolicy): self
    {
        if (is_null($this->currentException)) {
            throw new LogicException('Cannot associate a RetryPolicy without an Exception. Use `$builder->when($e)->then($r)`');
        }
        $this->exceptions[] = new RetriableException($this->currentException, $retryPolicy);
        $this->currentException = null;

        return $this;
    }

    public function build(): SelectByException
    {
        if (!is_null($this->currentException)) {
            throw new LogicException("Missing RetryPolicy to associate to {$this->currentException}. Missing one last `->then(RetryPolicy)`");
        }
        if (empty($this->exceptions)) {
            throw new LogicException('No retry policies has been specified. Use `$builder->when($e)->then($r)`');
        }

        return new SelectByException($this->exceptions);
    }
}
