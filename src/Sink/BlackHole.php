<?php

namespace Sink;

use Iterator;
use ArrayAccess;

class BlackHole implements Iterator, ArrayAccess
{
    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public function __set(string $name, mixed $value)
    {
    }

    public function __get(string $name): self
    {
        return $this;
    }

    public function __isset(string $name): bool
    {
        return false;
    }

    public function __unset(string $name): void
    {
    }

    public function __call(string $name, array $arguments)
    {
        return $this;
    }

    public function __toString(): string
    {
        return '';
    }

    public function __invoke(): self
    {
        return $this;
    }

    public function __clone(): void
    {
    }

    public static function __callStatic(string $name, array $args): self
    {
        return new self();
    }

    // Iterator Interface

    public function current(): self
    {
        return $this;
    }

    public function key(): self
    {
        return $this;
    }

    public function next(): void
    {
    }

    public function rewind(): void
    {
    }

    public function valid(): bool
    {
        return false;
    }

    // ArrayAccess Interface

    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $offset): self
    {
        return $this;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }
}
