<?php

declare(strict_types=1);

namespace Fixtures\NoComputedExpectedValue;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiscountCalculator
{
    public function discount(float $total): float
    {
        return $total >= 100 ? $total * 0.05 : 0.0;
    }
}

final class NoComputedExpectedValueTest extends TestCase
{
    public function testExpectedValueComputedWithMatch(): void
    {
        $total = 150.0;

        $result = (new DiscountCalculator())->discount($total);

        $expected = match (true) {
            $total >= 100 => $total * 0.05,
            default => 0.0,
        };
        self::assertSame($expected, $result);
    }

    public function testExpectedValueComputedInline(): void
    {
        $result = (new DiscountCalculator())->discount(150.0);

        $this->assertEquals(150.0 * 0.05, $result);
    }

    public function testExpectedValueComputedWithTernary(): void
    {
        $total = 50.0;

        $result = (new DiscountCalculator())->discount($total);

        self::assertSame($total >= 100 ? 5.0 : 0.0, $result);
    }

    public function testLiteralExpectedValue(): void
    {
        $result = (new DiscountCalculator())->discount(150.0);

        self::assertSame(7.5, $result);
    }

    #[DataProvider('provideTotals')]
    public function testExpectedValueFromAProvider(float $total, float $expectedDiscount): void
    {
        $result = (new DiscountCalculator())->discount($total);

        self::assertSame($expectedDiscount, $result);
    }

    /**
     * @return iterable<string, array{total: float, expectedDiscount: float}>
     */
    public static function provideTotals(): iterable
    {
        yield 'above the threshold' => ['total' => 150.0, 'expectedDiscount' => 7.5];
        yield 'below the threshold' => ['total' => 50.0, 'expectedDiscount' => 0.0];
    }
}
