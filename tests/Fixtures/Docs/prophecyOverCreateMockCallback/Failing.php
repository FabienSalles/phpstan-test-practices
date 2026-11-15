<?php

declare(strict_types=1);

namespace Docs\ProphecyOverCreateMockCallback\Failing;

use PHPUnit\Framework\TestCase;

interface ExchangeRates
{
    public function rate(string $currency): float;
}

final readonly class PriceConverter
{
    public function __construct(private ExchangeRates $rates)
    {
    }

    public function convert(float $amount, string $currency): float
    {
        return round($amount * $this->rates->rate($currency), 2);
    }
}

final class PriceConverterTest extends TestCase
{
    public function testConvertsWithTheRateOfTheCurrency(): void
    {
        $rates = $this->createMock(ExchangeRates::class);
        $rates->method('rate')->willReturnCallback(static fn (string $currency): float => $currency === 'USD' ? 1.1 : 0.9);

        $price = (new PriceConverter($rates))->convert(10.0, 'USD');

        self::assertSame(11.0, $price);
    }
}
