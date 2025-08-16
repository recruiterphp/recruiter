<?php

declare(strict_types=1);

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class LazyBones implements Workable
{
    use WorkableBehaviour;

    public static function waitFor(int $timeInSeconds, int $deltaInSeconds = 0): self
    {
        return new self($timeInSeconds * 1000000, $deltaInSeconds * 1000000);
    }

    public static function waitForMs($timeInMs, $deltaInMs = 0): self
    {
        return new self($timeInMs * 1000, $deltaInMs * 1000);
    }

    public function __construct(private readonly int $usToSleep = 1, private readonly int $usOfDelta = 0)
    {
    }

    public function execute(): void
    {
        usleep($this->usToSleep + random_int(intval(-$this->usOfDelta), $this->usOfDelta));
    }

    public function export(): array
    {
        return [
            'us_to_sleep' => $this->usToSleep,
            'us_of_delta' => $this->usOfDelta,
        ];
    }

    public static function import(array $parameters): static
    {
        return new static(
            $parameters['us_to_sleep'],
            $parameters['us_of_delta'],
        );
    }
}
