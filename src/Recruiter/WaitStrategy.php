<?php

declare(strict_types=1);

namespace Recruiter;

use Timeless\Interval;

class WaitStrategy
{
    private int $timeToWaitAtLeast;
    private int $timeToWaitAtMost;
    private int $timeToWait;
    private \Closure $howToWait;

    /**
     * @param callable|callable-string $howToWait
     */
    public function __construct(Interval $timeToWaitAtLeast, Interval $timeToWaitAtMost, callable|string $howToWait = 'usleep')
    {
        $this->timeToWaitAtLeast = $timeToWaitAtLeast->milliseconds();
        $this->timeToWaitAtMost = $timeToWaitAtMost->milliseconds();
        $this->timeToWait = $timeToWaitAtLeast->milliseconds();
        $this->howToWait = $howToWait(...);
    }

    /**
     * @return $this
     */
    public function reset(): static
    {
        $this->timeToWait = $this->timeToWaitAtLeast;

        return $this;
    }

    /**
     * @return $this
     */
    public function goForward(): static
    {
        $this->timeToWait = max(
            $this->timeToWait / 2,
            $this->timeToWaitAtLeast,
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function backOff(): static
    {
        $this->timeToWait = min(
            $this->timeToWait * 2,
            $this->timeToWaitAtMost,
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function wait(): static
    {
        call_user_func($this->howToWait, $this->timeToWait * 1000);

        return $this;
    }

    public function timeToWait(): Interval
    {
        return new Interval($this->timeToWait);
    }

    public function timeToWaitAtMost(): Interval
    {
        return new Interval($this->timeToWaitAtMost);
    }
}
