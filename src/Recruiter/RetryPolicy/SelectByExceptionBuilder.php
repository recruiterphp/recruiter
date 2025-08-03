<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;
use Symfony\Component\Console\Exception\LogicException;

class SelectByExceptionBuilder
{
    /**
     * @var array<RetriableException>
     */
    private $exceptions;

    /**
     * @var ?string
     */
    private $currentException;

    public function __construct()
    {
        $this->exceptions = [];
        $this->currentException = null;
    }

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
