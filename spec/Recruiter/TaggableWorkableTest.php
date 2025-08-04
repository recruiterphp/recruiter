<?php

namespace Recruiter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Recruiter\Job\Repository;

class TaggableWorkableTest extends TestCase
{
    private MockObject&Repository $repository;

    protected function setUp(): void
    {
        $this->repository = $this
                          ->getMockBuilder(Repository::class)
                          ->disableOriginalConstructor()
                          ->getMock()
        ;
    }

    public function testWorkableExportsTags(): void
    {
        $workable = new WorkableTaggable(['a', 'b']);
        $job = Job::around($workable, $this->repository);

        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['a', 'b'], $exported['tags']);
    }

    public function testCanSetTagsOnJobs(): void
    {
        $workable = new WorkableTaggable([]);
        $job = Job::around($workable, $this->repository);
        $job->taggedAs(['c']);

        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['c'], $exported['tags']);
    }

    public function testTagsAreMergedTogether(): void
    {
        $workable = new WorkableTaggable(['a', 'b']);
        $job = Job::around($workable, $this->repository);
        $job->taggedAs(['c']);

        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['a', 'b', 'c'], $exported['tags']);
    }

    public function testTagsAreUnique(): void
    {
        $workable = new WorkableTaggable(['c']);
        $job = Job::around($workable, $this->repository);
        $job->taggedAs(['c']);

        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['c'], $exported['tags']);
    }

    public function testEmptyTagsAreNotExported(): void
    {
        $workable = new WorkableTaggable([]);
        $job = Job::around($workable, $this->repository);

        $exported = $job->export();
        $this->assertArrayNotHasKey('tags', $exported);
    }

    public function testTagsAreImported(): void
    {
        $workable = new WorkableTaggable(['a', 'b']);
        $job = Job::around($workable, $this->repository);
        $job->taggedAs(['c']);

        $exported = $job->export();
        // Here tags will be imported at job level, Workable will
        // import its own tags to be able to respond to `taggedAs`,
        // so ['a', 'b', 'c'] will be imported at the job level and
        $job = Job::import($exported, $this->repository);

        // Here we will merge ['a', 'b', 'c'] at the job level with
        // ['a', 'b'] returned from `Workable::taggedAs`, the result
        // is always the same because tags are kept unique
        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['a', 'b', 'c'], $exported['tags']);
    }
}

class WorkableTaggable implements Workable, Taggable
{
    use WorkableBehaviour;

    public function __construct(private array $tags)
    {
    }

    public function taggedAs(): array
    {
        return $this->tags;
    }

    public function export(): array
    {
        return ['tags' => $this->tags];
    }

    public static function import(array $parameters): static
    {
        return new self($parameters['tags']);
    }

    public function execute()
    {
        // nothing is good
    }
}
