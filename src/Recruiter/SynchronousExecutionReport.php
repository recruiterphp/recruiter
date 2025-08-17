<?php

declare(strict_types=1);

namespace Recruiter;

/**
 * Class SynchronousExecutionReport.
 */
class SynchronousExecutionReport
{
    /**
     * @param array<string, JobExecution> $data
     */
    private function __construct(private readonly array $data)
    {
    }

    /**
     * @param array<string, JobExecution> $data the key is the Job ID
     */
    public static function fromArray(array $data): SynchronousExecutionReport
    {
        return new self($data);
    }

    public function isThereAFailure(): bool
    {
        return array_any($this->data, fn ($jobExecution, $jobId) => $jobExecution->isFailed());
    }

    /**
     * @return array<string, JobExecution> the key is the Job ID
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
