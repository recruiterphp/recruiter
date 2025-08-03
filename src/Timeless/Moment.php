<?php

namespace Timeless;

use DateTime;
use DateTimeZone;

readonly class Moment
{
    public static function fromTimestamp(int $ts): self
    {
        return new self($ts * 1000);
    }

    public static function fromDateTime(DateTime $dateTime): self
    {
        return self::fromTimestamp($dateTime->getTimestamp());
    }

    public function __construct(private int $ms)
    {
    }

    public function milliseconds(): int
    {
        return $this->ms;
    }

    public function ms(): int
    {
        return $this->ms;
    }

    public function seconds(): int
    {
        return $this->s();
    }

    public function s(): int
    {
        return (int) round($this->ms / 1000.0);
    }

    public function after(Interval $d): self
    {
        return new self($this->ms + $d->ms());
    }

    public function before(Interval $d): self
    {
        return new self($this->ms - $d->ms());
    }

    public function isAfter(Moment $m): bool
    {
        return $this->ms >= $m->ms();
    }

    public function isBefore(Moment $m): bool
    {
        return $this->ms <= $m->ms();
    }

    public function toSecondPrecision(): Moment
    {
        return new self($this->s() * 1000);
    }

    public function format(): string
    {
        return new DateTime('@' . $this->s(), new DateTimeZone('UTC'))->format(DateTime::RFC3339);
    }

    public function toDateTime(): DateTime
    {
        return new DateTime('@' . $this->s(), new DateTimeZone('UTC'));
    }
}
