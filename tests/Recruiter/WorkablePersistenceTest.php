<?php

declare(strict_types=1);

namespace Recruiter;

use PHPUnit\Framework\TestCase;

class WorkablePersistenceTest extends TestCase
{
    public function testCanBeExportedAndImported(): void
    {
        $job = new SomethingWorkable(['key' => 'value']);
        $this->assertEquals(
            $job,
            SomethingWorkable::import($job->export()),
        );
    }
}

class SomethingWorkable implements Workable
{
    use WorkableBehaviour;
}
