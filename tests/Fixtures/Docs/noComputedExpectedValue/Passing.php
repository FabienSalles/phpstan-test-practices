<?php

declare(strict_types=1);

namespace Docs\NoComputedExpectedValue\Passing;

use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('provideTotals')]
    public function testGivesFivePercentFromOneHundred(float $total, float $expectedDiscount): void
    {
        $discount = (new DiscountCalculator())->discount($total);

        self::assertSame($expectedDiscount, $discount);
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function provideTotals(): iterable
    {
        yield 'below the threshold' => [99.0, 0.0];
        yield 'at the threshold' => [100.0, 5.0];
        yield 'above the threshold' => [200.0, 10.0];
    }
}
