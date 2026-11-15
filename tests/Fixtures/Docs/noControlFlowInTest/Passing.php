<?php

declare(strict_types=1);

namespace Docs\NoControlFlowInTest\Passing;

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
    public function testSumsEveryPrice(): void
    {
        $cart = new Cart();
        $cart->add(10);
        $cart->add(5);

        $cart->add(20);

        self::assertSame(35, $cart->total());
    }
}
