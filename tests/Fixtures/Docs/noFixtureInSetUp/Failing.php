<?php

declare(strict_types=1);

namespace Docs\NoFixtureInSetUp\Failing;

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
    private Cart $cart;

    protected function setUp(): void
    {
        $this->cart = new Cart();
        $this->cart->add(10);
    }

    public function testAddsAPrice(): void
    {
        $this->cart->add(5);

        self::assertSame(15, $this->cart->total());
    }
}
