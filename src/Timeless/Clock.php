<?php

namespace Timeless;

class Clock implements ClockInterface
{
    public function start(): ClockInterface
    {
        return clock($this);
    }

    public function stop(): ClockInterface
    {
        return clock(new StoppedClock($this->now()));
    }

    public function now(): Moment
    {
        return new Moment((int) round(microtime(true) * 1000));
    }
}
