<?php

declare(strict_types=1);

namespace Recruiter\Workable;

use Random\RandomException;
use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class LazyBones implements Workable
{
    use WorkableBehaviour;

    public static function waitFor(int $timeInSeconds, int $deltaInSeconds = 0): self
    {
        return new self($timeInSeconds * 1000000, $deltaInSeconds * 1000000);
    }

    public static function waitForMs(int $timeInMs, int $deltaInMs = 0): self
    {
        return new self($timeInMs * 1000, $deltaInMs * 1000);
    }

    public function __construct(private readonly int $usToSleep = 1, private readonly int $usOfDelta = 0)
    {
    }

    /**
     * @throws RandomException
     */
    public function execute(): void
    {
        usleep($this->usToSleep + random_int(-$this->usOfDelta, $this->usOfDelta));
    }

    /**
     * @return array{us_to_sleep: int, us_of_delta: int}
     */
    public function export(): array
    {
        return [
            'us_to_sleep' => $this->usToSleep,
            'us_of_delta' => $this->usOfDelta,
        ];
    }

    /**
     * @param array{us_to_sleep: int, us_of_delta: int} $parameters
     */
    public static function import(array $parameters): static
    {
        return new static(
            $parameters['us_to_sleep'],
            $parameters['us_of_delta'],
        );
    }
}
