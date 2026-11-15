# `testPractices.noComputedExpectedValue`

No computed expected value. Enabled from `testPractices.level` **0** (see [levels](../../README.md#levels)).

Auto-fixable: no. The literal would have to be computed by running the formula, which is exactly what the test must not trust.

Related skill: [testing-principles › 2. What NOT to Test](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#2-what-not-to-test), [php-test-conventions › assertion-patterns › Asserting Computed Properties](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/references/assertion-patterns.md#asserting-computed-properties).

## What it detects

An `assertSame()` or `assertEquals()` whose expected value (the first argument, or the variable assigned to it in the test) contains a `match`, a ternary, or arithmetic (`+`, `-`, `*`, `/`, `%`, `**`).

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noComputedExpectedValue: warning
```

## Failing example

```php
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
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noComputedExpectedValue/Failing.php:25:Test testGivesFivePercentFromOneHundred() computes its expected value (arithmetic "*"). An expectation recomputed in the test mirrors the implementation; write the literal expected result.
```

## Passing example

```php
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
```

## Why

The expected value is the specification. When the test computes it with the same formula as the implementation, the test and the code agree by construction:

- **It cannot detect a wrong formula.** If the discount should be 5 % from 100 and the code applies `$total * 0.05` from 50, a test that expects `$total * 0.05` passes. The bug is copied into the test.
- **It survives the mutants it should kill.** Infection replaces `*` by `/` or `>=` by `>` in the code; when the test reproduces the expression, some mutants are mirrored in the expectation and not detected.
- **It hides the business rule.** `10.0` for a total of `200.0` says what the rule produces. `$total * 0.05` says how the code is written, and the reader still has to compute the answer.
- **It mixes logic into the test.** Conditional and arithmetic logic in a test is code that is not tested itself ([Testing on the Toilet: Don't Put Logic in Tests](https://testing.googleblog.com/2014/07/testing-on-toilet-dont-put-logic-in.html), Google). A data provider with one literal per case reads as a table of examples, and each row fails on its own.

Source practice: literal expected values; xUnit Test Patterns, Conditional Test Logic.

## Known limits

- A computation that is not the logic under test (building a date `+ 3600`, a fixture size) is reported when it ends up in the expected value. Compute the input instead, or ignore the line.
- An expected value computed in a helper method or a data provider is not seen.

## Ignoring

Use the identifier `testPractices.noComputedExpectedValue`, on the line:

```php
// @phpstan-ignore testPractices.noComputedExpectedValue
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noComputedExpectedValue
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noComputedExpectedValue/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
