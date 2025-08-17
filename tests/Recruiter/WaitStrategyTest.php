<?php

declare(strict_types=1);

namespace Recruiter;

use PHPUnit\Framework\TestCase;
use Timeless as T;

class WaitStrategyTest extends TestCase
{
    private T\Interval $waited;
    private \Closure $howToWait;
    private T\Interval $timeToWaitAtLeast;
    private T\Interval $timeToWaitAtMost;

    protected function setUp(): void
    {
        $this->waited = T\milliseconds(0);
        $this->howToWait = function (int $microseconds): void {
            $this->waited = T\milliseconds($microseconds / 1000);
        };
        $this->timeToWaitAtLeast = T\milliseconds(250);
        $this->timeToWaitAtMost = T\seconds(30);
    }

    public function testStartsToWaitTheMinimumAmountOfTime(): void
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait,
        );
        $ws->wait();
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
    }

    public function testBackingOffIncreasesTheIntervalExponentially(): void
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait,
        );
        $ws->wait();
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
        $ws->backOff()->wait();
        $this->assertEquals($this->timeToWaitAtLeast->multiplyBy(2), $this->waited);
        $ws->backOff()->wait();
        $this->assertEquals($this->timeToWaitAtLeast->multiplyBy(4), $this->waited);
    }

    public function testBackingOffCannotIncreaseTheIntervalOverAMaximum(): void
    {
        $ws = new WaitStrategy(T\seconds(1), T\seconds(2), $this->howToWait);
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->wait();
        $this->assertEquals(T\seconds(2), $this->waited);
    }

    public function testGoingForwardLowersTheSleepingPeriod(): void
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait,
        );
        $ws->backOff();
        $ws->goForward();
        $ws->wait();
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
    }

    public function testTheSleepingPeriodCanBeResetToTheMinimum(): void
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait,
        );
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->reset();
        $ws->wait();
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
    }

    public function testGoingForwardCannotLowerTheIntervalBelowMinimum(): void
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait,
        );
        $ws->goForward();
        $ws->goForward();
        $ws->goForward();
        $ws->goForward();
        $ws->wait();
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
    }
}
