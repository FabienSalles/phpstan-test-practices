<?php

declare(strict_types=1);

namespace Docs\NoComputedExpectedValue\Failing;

use PHPUnit\Framework\TestCase;

final class DiscountCalculator
{
    public function discount(float $total): float
    {
        return $total >= 100 ? $total * 0.05 : 0.0;
    }
}

final class DiscountCalculatorTest extends TestCase
{
    public function testGivesFivePercentFromOneHundred(): void
    {
        $total = 200.0;

        $discount = (new DiscountCalculator())->discount($total);

        self::assertSame($total * 0.05, $discount);
    }
}
