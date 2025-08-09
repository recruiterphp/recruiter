<?php

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class ShellCommand implements Workable
{
    use WorkableBehaviour;

    public static function fromCommandLine(string $commandLine): self
    {
        return new self($commandLine);
    }

    private function __construct(private string $commandLine)
    {
    }

    public function execute(): string
    {
        exec($this->commandLine, $output, $returnCode);
        $output = implode(PHP_EOL, $output);
        if (0 != $returnCode) {
            throw new \RuntimeException("Command execution failed (return code $returnCode). Output: " . $output);
        }

        return $output;
    }

    /**
     * @return array{command: string}
     */
    public function export(): array
    {
        return ['command' => $this->commandLine];
    }

    /**
     * @param array{command: string} $parameters
     */
    public static function import(array $parameters): static
    {
        return new static($parameters['command']);
    }
}
