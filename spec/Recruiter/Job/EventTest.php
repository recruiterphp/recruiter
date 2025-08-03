<?php

namespace Recruiter\Job;

use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testHasTagReturnsTrueWhenTheExportedJobContainsTheTag(): void
    {
        $event = new Event([
            'group' => 'generic',
            'tags' => [
                1 => 'billing-notification',
            ],
        ]);

        $this->assertTrue($event->hasTag('billing-notification'));
    }

    public function testHasTagReturnsFalseWhenTheExportedJobDoesNotContainTheTag(): void
    {
        $event = new Event([
            'group' => 'generic',
            'tags' => [
                1 => 'billing-notification',
            ],
        ]);

        $this->assertFalse($event->hasTag('inexistant-tag'));
    }

    public function testHasTagReturnsFalseWhenTheExportedJobDoesNotContainTags(): void
    {
        $event = new Event([
        ]);

        $this->assertFalse($event->hasTag('inexistant-tag'));
    }
}
