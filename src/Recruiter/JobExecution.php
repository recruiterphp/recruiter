<?php

declare(strict_types=1);

namespace Recruiter;

use MongoDB\BSON\UTCDateTime;
use Timeless as T;

class JobExecution
{
    private bool $isCrashed = false;
    private ?T\Moment $scheduledAt = null;
    private ?T\Moment $startedAt = null;
    private ?T\Moment $endedAt = null;
    private mixed $completedWith = null;
    private ?\Throwable $failedWith = null;

    public function isCrashed(): bool
    {
        return $this->isCrashed;
    }

    public function started(?T\Moment $scheduledAt = null): void
    {
        $this->scheduledAt = $scheduledAt;
        $this->startedAt = T\now();
    }

    public function failedWith(\Throwable $exception): void
    {
        $this->endedAt = T\now();
        $this->failedWith = $exception;
    }

    public function completedWith(mixed $result): void
    {
        $this->endedAt = T\now();
        $this->completedWith = $result;
    }

    public function result(): mixed
    {
        return $this->completedWith;
    }

    public function causeOfFailure(): ?\Throwable
    {
        return $this->failedWith;
    }

    public function isFailed(): bool
    {
        return !is_null($this->failedWith) || $this->isCrashed();
    }

    public function duration(): T\Interval
    {
        if ($this->startedAt && $this->endedAt && ($this->startedAt <= $this->endedAt)) {
            return T\seconds(
                $this->endedAt->seconds() -
                $this->startedAt->seconds(),
            );
        }

        return T\seconds(0);
    }

    /**
     * @param array{
     *     last_execution?: array{
     *         crashed?: bool,
     *         scheduled_at?: UTCDateTime,
     *         started_at?: UTCDateTime,
     *         ended_at?: UTCDateTime,
     *     }
     * } $document
     */
    public static function import(array $document): self
    {
        $lastExecution = new self();
        if (array_key_exists('last_execution', $document)) {
            $lastExecutionDocument = $document['last_execution'];
            if (array_key_exists('crashed', $lastExecutionDocument)) {
                $lastExecution->isCrashed = true;
            }
            if (array_key_exists('scheduled_at', $lastExecutionDocument)) {
                $lastExecution->scheduledAt = T\MongoDate::toMoment($lastExecutionDocument['scheduled_at']);
            }
            if (array_key_exists('started_at', $lastExecutionDocument)) {
                $lastExecution->startedAt = T\MongoDate::toMoment($lastExecutionDocument['started_at']);
            }
        }

        return $lastExecution;
    }

    /**
     * @return array{
     *     last_execution?: array{
     *         scheduled_at?: UTCDateTime,
     *         started_at?: UTCDateTime,
     *         ended_at?: UTCDateTime,
     *         class?: string,
     *         message?: string,
     *         trace?: string,
     *     }
     * }
     */
    public function export(): array
    {
        $exported = [];
        if ($this->scheduledAt) {
            $exported['scheduled_at'] = T\MongoDate::from($this->scheduledAt);
        }
        if ($this->startedAt) {
            $exported['started_at'] = T\MongoDate::from($this->startedAt);
        }
        if ($this->endedAt && !$this->isCrashed) {
            $exported['ended_at'] = T\MongoDate::from($this->endedAt);
        }
        if ($this->failedWith) {
            $exported['class'] = $this->failedWith::class;
            $exported['message'] = $this->failedWith->getMessage();
            $exported['trace'] = $this->traceOf($this->failedWith);
        }
        if ($this->completedWith) {
            $exported['trace'] = $this->traceOf($this->completedWith);
        }
        if ($exported) {
            return ['last_execution' => $exported];
        } else {
            return [];
        }
    }

    private function traceOf(mixed $result): string
    {
        $trace = 'ok';
        if ($result instanceof \Throwable) {
            $trace = $result->getTraceAsString();
        } elseif (is_object($result) && method_exists($result, 'trace')) {
            /** @var scalar $trace */
            $trace = $result->trace();
        } elseif (is_object($result)) {
            $trace = $result::class;
        } elseif (is_string($result) || is_numeric($result)) {
            $trace = $result;
        }

        return substr((string) $trace, 0, 4096);
    }
}
