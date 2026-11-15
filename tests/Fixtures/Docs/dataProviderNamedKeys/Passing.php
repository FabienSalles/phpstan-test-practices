<?php

declare(strict_types=1);

namespace Docs\DataProviderNamedKeys\Passing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiscountEligibility
{
    public function isEligible(int $percentage): bool
    {
        return $percentage >= 10;
    }
}

final class DiscountEligibilityTest extends TestCase
{
    #[DataProvider('providePercentages')]
    public function testDecidesTheEligibility(int $percentage, bool $expected): void
    {
        $eligible = (new DiscountEligibility())->isEligible($percentage);

        self::assertSame($expected, $eligible);
    }

    /**
     * @return iterable<string, array{percentage: int, expected: bool}>
     */
    public static function providePercentages(): iterable
    {
        yield '0%, not eligible' => ['percentage' => 0, 'expected' => false];
        yield '10%, eligible' => ['percentage' => 10, 'expected' => true];
    }
}
