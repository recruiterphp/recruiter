<?php

declare(strict_types=1);

namespace Timeless;

class StoppedClock implements ClockInterface
{
    public function __construct(private Moment $now)
    {
    }

    public function now(): Moment
    {
        return $this->now;
    }

    public function driftForwardBySeconds(int $seconds): void
    {
        $this->now = $this->now->after(seconds($seconds));
    }

    public function start(): Clock
    {
        clock($clock = new Clock());

        return $clock;
    }

    public function stop(): self
    {
        clock($this);

        return $this;
    }
}
