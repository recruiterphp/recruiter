<?php

namespace Timeless;

function clock(?ClockInterface $clock = null): ClockInterface
{
    global $__2852bec4cda046fca0e5e21dc007935c;
    /** @var ClockInterface $__2852bec4cda046fca0e5e21dc007935c */
    $__2852bec4cda046fca0e5e21dc007935c =
        $clock ?: (
            $__2852bec4cda046fca0e5e21dc007935c ?: new Clock()
        );
    return $__2852bec4cda046fca0e5e21dc007935c;
}

function now(): Moment
{
    return clock()->now();
}

function millisecond(int $numberOf): Interval
{
    return milliseconds($numberOf);
}

function milliseconds(int $numberOf): Interval
{
    return new Interval($numberOf);
}

function second(int $numberOf): Interval
{
    return seconds($numberOf);
}

function seconds(int $numberOf): Interval
{
    return new Interval($numberOf * Interval::MILLISECONDS_IN_SECONDS);
}

function minute(int $numberOf): Interval
{
    return minutes($numberOf);
}

function minutes(int $numberOf): Interval
{
    return new Interval($numberOf * Interval::MILLISECONDS_IN_MINUTES);
}

function hour(int $numberOf): Interval
{
    return hours($numberOf);
}

function hours(int $numberOf): Interval
{
    return new Interval($numberOf * Interval::MILLISECONDS_IN_HOURS);
}

function day(int $numberOf): Interval
{
    return days($numberOf);
}

function days(int $numberOf): Interval
{
    return new Interval($numberOf * Interval::MILLISECONDS_IN_DAYS);
}

function week(int $numberOf): Interval
{
    return weeks($numberOf);
}

function weeks(int $numberOf): Interval
{
    return new Interval($numberOf * Interval::MILLISECONDS_IN_WEEKS);
}

function month(int $numberOf): Interval
{
    return months($numberOf);
}

function months(int $numberOf): Interval
{
    return new Interval($numberOf * Interval::MILLISECONDS_IN_MONTHS);
}

function year(int $numberOf): Interval
{
    return years($numberOf);
}

function years(int $numberOf): Interval
{
    return new Interval($numberOf * Interval::MILLISECONDS_IN_YEARS);
}

function fromDateInterval(\DateInterval $interval): Interval
{
    $seconds = (string) $interval->s;
    if ($interval->i) {
        $seconds = bcadd($seconds, bcmul((string) $interval->i, '60'));
    }
    if ($interval->h) {
        $seconds = bcadd($seconds, bcmul((string) $interval->h, '3600'));
    }
    if ($interval->d) {
        $seconds = bcadd($seconds, bcmul((string) $interval->d, '86400'));
    }
    if ($interval->m) {
        $seconds = bcadd($seconds, bcmul((string) $interval->m, '2629740'));
    }
    if ($interval->y) {
        $seconds = bcadd($seconds, bcmul((string) $interval->y, '31556874'));
    }

    return seconds($seconds);
}
