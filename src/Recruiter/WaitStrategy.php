<?php

namespace Recruiter;

use Timeless\Interval;

class WaitStrategy
{
    private int $timeToWaitAtLeast;
    private int $timeToWaitAtMost;
    private int $timeToWait;

    public function __construct(Interval $timeToWaitAtLeast, Interval $timeToWaitAtMost, private string $howToWait = 'usleep')
    {
        $this->timeToWaitAtLeast = $timeToWaitAtLeast->milliseconds();
        $this->timeToWaitAtMost = $timeToWaitAtMost->milliseconds();
        $this->timeToWait = $timeToWaitAtLeast->milliseconds();
    }

    public function reset(): self
    {
        $this->timeToWait = $this->timeToWaitAtLeast;

        return $this;
    }

    public function goForward(): self
    {
        $this->timeToWait = max(
            $this->timeToWait / 2,
            $this->timeToWaitAtLeast,
        );

        return $this;
    }

    public function backOff(): self
    {
        $this->timeToWait = min(
            $this->timeToWait * 2,
            $this->timeToWaitAtMost,
        );

        return $this;
    }

    public function wait(): self
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
