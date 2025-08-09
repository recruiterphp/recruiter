<?php

namespace Recruiter\Job;

use Symfony\Contracts\EventDispatcher;

class Event extends EventDispatcher\Event
{
    /**
     * @param array<string, mixed> $jobExport
     */
    public function __construct(private readonly array $jobExport)
    {
    }

    /**
     * @return array<string, mixed>
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
