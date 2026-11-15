<?php

declare(strict_types=1);

namespace Fixtures\TestMethodIsDiscoverable;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

trait TotalAssertions
{
    private function assertTotalFromTrait(int $total): void
    {
        self::assertSame(3, $total);
    }

    private function assertUnusedFromTrait(int $total): void
    {
        self::assertSame(4, $total);
    }
}

abstract class CartTestCase extends TestCase
{
    protected function assertTotalFromParent(int $total): void
    {
        self::assertSame(3, $total);
    }
}

final class CartTest extends CartTestCase
{
    use TotalAssertions;

    protected function setUp(): void
    {
        self::assertTrue(true);
    }

    public function testUsesHelpers(): void
    {
        $total = 3;

        $this->assertTotal($total);
        $this->assertTotalFromParent($total);
        $this->assertTotalFromTrait($total);
    }

    #[Test]
    public function usesACallback(): void
    {
        $totals = [3];

        array_map([$this, 'assertTotalThroughCallback'], $totals);
    }

    /**
     * @test
     */
    public function isAnnotated(): void
    {
        self::assertTrue(true);
    }

    #[DataProvider('provideTotals')]
    public function testWithProvider(int $total): void
    {
        self::assertSame(3, $total);
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideTotals(): iterable
    {
        yield [3];
    }

    public function checksTheTotal(): void
    {
        self::assertSame(3, 1 + 2);
    }

    private function assertTotal(int $total): void
    {
        $this->assertTotalTransitively($total);
    }

    private function assertTotalTransitively(int $total): void
    {
        self::assertSame(3, $total);
    }

    private function assertTotalThroughCallback(int $total): void
    {
        self::assertSame(3, $total);
    }

    private function verifiesTheName(): void
    {
        self::assertSame('cart', 'cart');
    }
}
