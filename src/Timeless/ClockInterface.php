<?php

namespace Timeless;

interface ClockInterface
{
    public function now(): Moment;

    public function start(): ClockInterface;

    public function stop(): ClockInterface;
}
