<?php

declare(strict_types=1);

namespace Docs\NoTestCaseConstructor\Failing;

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

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->cart = new Cart();
    }

    public function testAddsAPrice(): void
    {
        $this->cart->add(5);

        self::assertSame(5, $this->cart->total());
    }
}
