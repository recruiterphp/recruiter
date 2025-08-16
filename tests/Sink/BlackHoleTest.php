<?php

declare(strict_types=1);

namespace Sink;

use PHPUnit\Framework\TestCase;

class BlackHoleTest extends TestCase
{
    public function testMethodCall(): void
    {
        $instance = new BlackHole();
        $this->assertInstanceOf(BlackHole::class, $instance->whateverMethod());
    }

    public function testGetter(): void
    {
        $instance = new BlackHole();
        $this->assertInstanceOf(BlackHole::class, $instance->whateverProperty);
    }

    public function testSetterReturnsTheValue(): void
    {
        $instance = new BlackHole();
        $this->assertEquals(42, $instance->whateverProperty = 42);
    }

    public function testNothingIsSet(): void
    {
        $instance = new BlackHole();
        $instance->whateverProperty = 42;
        $this->assertFalse(isset($instance->whateverProperty));
    }

    public function testToString(): void
    {
        $instance = new BlackHole();
        $this->assertEquals('', (string) $instance);
    }

    public function testInvoke(): void
    {
        $instance = new BlackHole();
        $this->assertInstanceOf(BlackHole::class, $instance());
    }

    public function testCallStatic(): void
    {
        $instance = BlackHole::whateverStaticMethod();
        $this->assertInstanceOf(BlackHole::class, $instance);
    }

    public function testIsIterableButItIsAlwaysEmpty(): void
    {
        $instance = new BlackHole();
        $this->assertEmpty(iterator_to_array($instance));
    }

    public function testIsAccessibleAsAnArrayAlwaysGetItself(): void
    {
        $instance = new BlackHole();
        $this->assertInstanceOf(BlackHole::class, $instance[42]);
        $this->assertInstanceOf(BlackHole::class, $instance['aString']);
        $this->assertInstanceOf(BlackHole::class, $instance[[1, 2, 3]]);
    }

    /* public function testIsAccessibleAsAnArrayExists() */
    /* { */
    /*     $instance = new BlackHole(); */
    /*     $this->assertFalse(array_key_exists(42, $instance)); */
    /* } */
}
