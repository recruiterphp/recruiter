<?php

declare(strict_types=1);

namespace Timeless;

class Clock implements ClockInterface
{
    public function start(): self
    {
        clock($this);

        return $this;
    }

    public function stop(): StoppedClock
    {
        clock($clock = new StoppedClock($this->now()));

        return $clock;
    }

    public function now(): Moment
    {
        return new Moment((int) round(microtime(true) * 1000));
    }
}
