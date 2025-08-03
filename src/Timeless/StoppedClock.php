<?php

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

    public function start(): ClockInterface
    {
        return clock(new Clock());
    }

    public function stop(): ClockInterface
    {
        return clock($this);
    }
}
