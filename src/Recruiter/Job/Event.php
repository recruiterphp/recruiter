<?php

declare(strict_types=1);

namespace Recruiter\Job;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Contracts\EventDispatcher;

class Event extends EventDispatcher\Event
{
    /**
     * @param array{
     *     _id: ObjectId,
     *     done: bool,
     *     created_at: UTCDateTime,
     *     scheduled_at?: UTCDateTime,
     *     locked: bool,
     *     attempts: int,
     *     group: string,
     *     tags?: string[],
     *     workable: array{
     *         method: string,
     *         class?: class-string,
     *         parameters?: array<mixed>,
     *     },
     *     last_execution?: array{
     *         started_at: UTCDateTime,
     *         ended_at: UTCDateTime,
     *         crashed: bool,
     *         duration: int,
     *         result: mixed,
     *         class?: class-string,
     *         message?: string,
     *         trace?: string,
     *     },
     *     scheduled?: array{
     *         by: array{
     *             namespace: string,
     *             urn: string,
     *         },
     *         executions: int,
     *     },
     *     scheduled?: array{
     *         by: array{
     *             namespace: string,
     *             urn: string,
     *         },
     *         executions: int,
     *     },
     *     retry_policy?: array<string, mixed>,
     *     why?: string
     * } $jobExport
     */
    public function __construct(private readonly array $jobExport)
    {
    }

    /**
     * @return array{
     *     _id: ObjectId,
     *     done: bool,
     *     created_at: UTCDateTime,
     *     scheduled_at?: UTCDateTime,
     *     locked: bool,
     *     attempts: int,
     *     group: string,
     *     tags?: string[],
     *     workable: array{
     *         method: string,
     *         class?: class-string,
     *         parameters?: array<mixed>,
     *     },
     *     last_execution?: array{
     *         started_at: UTCDateTime,
     *         ended_at: UTCDateTime,
     *         crashed: bool,
     *         duration: int,
     *         result: mixed,
     *         class?: class-string,
     *         message?: string,
     *         trace?: string,
     *     },
     *     scheduled?: array{
     *         by: array{
     *             namespace: string,
     *             urn: string,
     *         },
     *         executions: int,
     *     },
     *     retry_policy?: array<string, mixed>,
     *     why?: string
     * }
     */
    public function export(): array
    {
        return $this->jobExport;
    }

    public function hasTag(string $wantedTag): bool
    {
        $tags = array_key_exists('tags', $this->jobExport) ? $this->jobExport['tags'] : [];

        return in_array($wantedTag, $tags);
    }
}
