<?php

declare(strict_types=1);

namespace Docs\ProphecyOverCreateMockCallback\Passing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

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
    use ProphecyTrait;

    public function testConvertsWithTheRateOfTheCurrency(): void
    {
        $rates = $this->prophesize(ExchangeRates::class);
        $rates->rate('USD')->willReturn(1.1);
        $rates->rate('GBP')->willReturn(0.9);

        $price = (new PriceConverter($rates->reveal()))->convert(10.0, 'USD');

        self::assertSame(11.0, $price);
    }
}
