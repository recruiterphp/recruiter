<?php

declare(strict_types=1);

namespace Timeless;

interface ClockInterface
{
    public function now(): Moment;

    public function start(): Clock;

    public function stop(): StoppedClock;
}
