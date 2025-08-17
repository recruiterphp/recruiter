<?php

declare(strict_types=1);

namespace Recruiter\Job;

use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testHasTagReturnsTrueWhenTheExportedJobContainsTheTag(): void
    {
        $event = new Event([
            '_id' => new \MongoDB\BSON\ObjectId(),
            'done' => false,
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
            'locked' => false,
            'attempts' => 0,
            'group' => 'generic',
            'tags' => [
                1 => 'billing-notification',
            ],
            'workable' => ['method' => 'execute'],
        ]);

        $this->assertTrue($event->hasTag('billing-notification'));
    }

    public function testHasTagReturnsFalseWhenTheExportedJobDoesNotContainTheTag(): void
    {
        $event = new Event([
            '_id' => new \MongoDB\BSON\ObjectId(),
            'done' => false,
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
            'locked' => false,
            'attempts' => 0,
            'group' => 'generic',
            'tags' => [
                1 => 'billing-notification',
            ],
            'workable' => ['method' => 'execute'],
        ]);

        $this->assertFalse($event->hasTag('inexistant-tag'));
    }

    public function testHasTagReturnsFalseWhenTheExportedJobDoesNotContainTags(): void
    {
        $event = new Event([
            '_id' => new \MongoDB\BSON\ObjectId(),
            'done' => false,
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
            'locked' => false,
            'attempts' => 0,
            'group' => 'generic',
            'workable' => ['method' => 'execute'],
        ]);

        $this->assertFalse($event->hasTag('inexistant-tag'));
    }
}
