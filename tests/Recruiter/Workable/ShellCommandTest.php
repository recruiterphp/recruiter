<?php

declare(strict_types=1);

namespace Recruiter\Workable;

use PHPUnit\Framework\TestCase;

class ShellCommandTest extends TestCase
{
    public function testExecutesACommandOnTheShell(): void
    {
        $workable = ShellCommand::fromCommandLine('echo 42');
        $this->assertEquals('42', $workable->execute());
    }

    public function testCanBeImportedAndExported(): void
    {
        $workable = ShellCommand::fromCommandLine('echo 42');
        $this->assertEquals(
            $workable,
            ShellCommand::import($workable->export()),
        );
    }
}
