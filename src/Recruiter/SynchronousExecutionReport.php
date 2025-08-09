<?php

declare(strict_types=1);

namespace Recruiter;

/**
 * Class SynchronousExecutionReport.
 */
final readonly class SynchronousExecutionReport
{
    /**
     * @param array<string, JobExecution> $data
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * @param array<string, JobExecution> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function isThereAFailure(): bool
    {
        return array_any($this->data, fn ($jobExecution, $jobId) => $jobExecution->isFailed());
    }

    /**
     * @return array<string, JobExecution>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
