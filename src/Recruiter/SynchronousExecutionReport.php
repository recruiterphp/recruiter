<?php

declare(strict_types=1);

namespace Recruiter;

/**
 * Class SynchronousExecutionReport.
 */
class SynchronousExecutionReport
{
    /**
     * @param array $data = []
     */
    public function __construct(private readonly array $data = [])
    {
    }

    /**
     *. @params array $data : key value array where key are the id of the job and value is the JobExecution.
     */
    public static function fromArray(array $data): SynchronousExecutionReport
    {
        return new self($data);
    }

    public function isThereAFailure(): bool
    {
        return array_any($this->data, fn ($jobExecution, $jobId) => $jobExecution->isFailed());
    }

    public function toArray()
    {
        return $this->data;
    }
}
