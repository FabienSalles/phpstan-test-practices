<?php

declare(strict_types=1);

namespace Fixtures\NoReflectionOnSut;

use PHPUnit\Framework\TestCase;

final class Cart
{
    private int $total = 0;

    public function add(int $price): void
    {
        $this->total = $this->rounded($this->total + $price);
    }

    public function total(): int
    {
        return $this->total;
    }

    private function rounded(int $amount): int
    {
        return $amount;
    }
}

final class NoReflectionOnSutTest extends TestCase
{
    public function testCallsAPrivateMethod(): void
    {
        $method = new \ReflectionMethod(Cart::class, 'rounded');
        $method->setAccessible(true);

        $rounded = $method->invoke(new Cart(), 5);

        self::assertSame(5, $rounded);
    }

    public function testReadsAPrivateProperty(): void
    {
        $cart = new Cart();
        $cart->add(5);

        $total = (new \ReflectionProperty(Cart::class, 'total'))->getValue($cart);

        self::assertSame(5, $total);
    }

    public function testBindsAClosure(): void
    {
        $cart = new Cart();

        $total = \Closure::bind(static fn (Cart $cart): int => $cart->total, null, Cart::class)($cart);

        self::assertSame(0, $total);
    }

    public function testUsesThePublicApi(): void
    {
        $cart = new Cart();
        $reflection = new \ReflectionClass(Cart::class);

        $cart->add(5);

        self::assertSame([5, 'Cart'], [$cart->total(), $reflection->getShortName()]);
    }
}
