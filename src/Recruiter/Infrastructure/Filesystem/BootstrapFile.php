<?php

declare(strict_types=1);

namespace Recruiter\Infrastructure\Filesystem;

use Recruiter\Recruiter;

/**
 * Class BootstrapFile.
 */
class BootstrapFile
{
    public function __construct(private readonly string $filePath)
    {
        $this->validate($filePath);
    }

    public static function fromFilePath(string $filePath): self
    {
        return new static($filePath);
    }

    public function load(Recruiter $recruiter)
    {
        return require $this->filePath;
    }

    private function validate(string $filePath): void
    {
        if (!file_exists($filePath)) {
            $this->throwBecauseFile($filePath, "doesn't exists");
        }

        if (!is_readable($filePath)) {
            $this->throwBecauseFile($filePath, 'is not readable');
        }
    }

    private function throwBecauseFile(string $filePath, string $reason): never
    {
        throw new \UnexpectedValueException(sprintf("Bootstrap file has an invalid value: file '%s' %s", $filePath, $reason));
    }
}
