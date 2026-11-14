<?php

declare(strict_types=1);

namespace Docs\TestMethodIsDiscoverable\Passing;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Cart
{
    /** @var list<int> */
    private array $prices = [];

    public function add(int $price): void
    {
        $this->prices[] = $price;
    }

    public function total(): int
    {
        return array_sum($this->prices);
    }
}

final class CartTest extends TestCase
{
    #[Test]
    public function sumsThePrices(): void
    {
        $cart = new Cart();
        $cart->add(10);

        $cart->add(5);

        $this->assertTotal(15, $cart);
    }

    private function assertTotal(int $expected, Cart $cart): void
    {
        self::assertSame($expected, $cart->total());
    }
}
