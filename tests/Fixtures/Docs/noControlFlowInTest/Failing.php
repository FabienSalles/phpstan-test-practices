<?php

declare(strict_types=1);

namespace Docs\NoControlFlowInTest\Failing;

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

        foreach ([10, 5, 20] as $price) {
            $cart->add($price);
        }

        self::assertSame(35, $cart->total());
    }
}
