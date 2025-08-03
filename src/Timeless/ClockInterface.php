<?php

namespace Timeless;

interface ClockInterface
{
    public function now(): Moment;
}