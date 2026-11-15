# `testPractices.prophecyOverCreateMockCallback`

Prophecy over a scripted PHPUnit double. Enabled from `testPractices.level` **3** (see [levels](../../README.md#levels)).

Auto-fixable: no. Rewriting a callback into Prophecy promises means splitting its branches into one promise per argument.

Related skill: [php-test-conventions › PHP-specific: Test Doubles](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/SKILL.md#php-specific-test-doubles).

## What it detects

A PHPUnit double (`createMock()`, `createStub()`, `getMockBuilder()`, `createPartialMock()`, `createConfigured*()`) stored in a variable of a test method, then scripted with `willReturnCallback()` or `will()`.

Skipped entirely when Prophecy is not installed (no `Prophecy\Prophet` class), and when the doubled class is final and implements no interface, since Prophecy cannot double it.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            prophecyOverCreateMockCallback: error
```

## Failing example

```php
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
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/prophecyOverCreateMockCallback/Failing.php:31:Test testConvertsWithTheRateOfTheCurrency() scripts a PHPUnit double with willReturnCallback(): a double that needs behaviour reads better as a Prophecy double or a hand-written one.
```

## Passing example

```php
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
```

## Why

A callback turns a double into a small program inside the test:

- **Readability.** `willReturnCallback(fn ($currency) => $currency === 'USD' ? 1.1 : 0.9)` has to be executed in the head to know what the double answers. Two Prophecy promises, `rate('USD')->willReturn(1.1)` and `rate('GBP')->willReturn(0.9)`, state it.
- **Logic in tests.** The callback is conditional logic that nothing tests; a wrong branch silently changes the scenario.
- **Unexpected calls.** A Prophecy double with promises fails or returns `null` for an argument nobody planned, which shows up; a catch-all callback answers everything.

When the behaviour is richer than a few promises, a hand-written in-memory implementation is clearer than both.

Source practice: a double that needs scripting is a Prophecy double; PHPUnit doubles where Prophecy cannot go (final classes without interface).

## Known limits

- Reported as a warning: `willReturnCallback()` is idiomatic PHPUnit.
- A double scripted through a property or a helper method is not followed.

## Ignoring

Use the identifier `testPractices.prophecyOverCreateMockCallback`, on the line:

```php
// @phpstan-ignore testPractices.prophecyOverCreateMockCallback
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.prophecyOverCreateMockCallback
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/prophecyOverCreateMockCallback/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
