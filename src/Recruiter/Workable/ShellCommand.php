<?php

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class ShellCommand implements Workable
{
    use WorkableBehaviour;

    public static function fromCommandLine($commandLine)
    {
        return new self($commandLine);
    }

    private function __construct(private $commandLine)
    {
    }

    public function execute()
    {
        exec($this->commandLine, $output, $returnCode);
        $output = implode(PHP_EOL, $output);
        if (0 != $returnCode) {
            throw new \RuntimeException("Command execution failed (return code $returnCode). Output: " . $output);
        }

        return $output;
    }

    public function export(): array
    {
        return ['command' => $this->commandLine];
    }

    public static function import(array $parameters): static
    {
        return new self($parameters['command']);
    }
}
