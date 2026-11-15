# `testPractices.dataProviderNamedKeys`

Named data provider keys. Enabled from `testPractices.level` **2** (see [levels](../../README.md#levels)).

Auto-fixable: no. A name has to describe the case, which only the author can write.

Related skill: [php-test-conventions › PHP-specific: Data Providers (PHPUnit syntax)](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/SKILL.md#php-specific-data-providers-phpunit-syntax), [testing-principles › 7. Parameterized Tests (data provider / it.each)](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#7-parameterized-tests-data-provider--iteach).

## What it detects

In a data provider of the same class (named by `#[DataProvider('...')]` or `@dataProvider`), a `yield` without a string key, or an item without a string key in a returned array literal. `yield from` and `#[TestWith]` are not checked.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            dataProviderNamedKeys: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\DataProviderNamedKeys\Failing;

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
     * @return iterable<array{int, bool}>
     */
    public static function providePercentages(): iterable
    {
        yield [0, false];
        yield [10, true];
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/dataProviderNamedKeys/Failing.php:33:Data provider providePercentages() yields a data set without a name: PHPUnit reports it as "data set #0".
tests/Fixtures/Docs/dataProviderNamedKeys/Failing.php:34:Data provider providePercentages() yields a data set without a name: PHPUnit reports it as "data set #0".
```

## Passing example

```php
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
```

## Why

A data provider is a table of examples; each row is a case of the specification:

- **Failure messages.** PHPUnit names a failing row by its key. `with data set #3` sends the reader counting rows; `with data set "10%, eligible"` says which rule broke.
- **Readability.** The key is the place to say why the row exists ("just below the threshold"), which the values alone do not.
- **Stability.** Inserting a row renumbers every unnamed row after it, so the same failure changes name between two runs of the history.

Named arguments in the row (`'percentage' => 0`) add the same benefit for the columns.

Source practice: `yield 'label' => [...]` in data providers.

## Known limits

- Data providers in another class (`#[DataProviderExternal]`) are not checked.
- A key built from a constant or an expression is reported as unnamed.

## Ignoring

Use the identifier `testPractices.dataProviderNamedKeys`, on the line:

```php
// @phpstan-ignore testPractices.dataProviderNamedKeys
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.dataProviderNamedKeys
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/dataProviderNamedKeys/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
