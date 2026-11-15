# `testPractices.testClassSuffix`

Test class suffix. Enabled from `testPractices.level` **0** (see [levels](../../README.md#levels)).

Auto-fixable: no. Renaming the class means renaming its file, which a PHPStan fix cannot do.

Related skill: none: no section of the [marketplace skills](https://github.com/FabienSalles/claude-marketplace) states this practice yet.

## What it detects

A concrete (non-abstract, non-anonymous) `TestCase` subclass whose name does not end with `testClassSuffix.suffix` (`Test` by default). Abstract base classes (`OrderTestCase`) are not reported.

## Severity

Default severity: **error**. Change it, or the suffix, in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            testClassSuffix: warning
        testClassSuffix:
            suffix: Test
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\TestClassSuffix\Failing;

use PHPUnit\Framework\TestCase;

final class Cart
{
    /** @var list<int> */
    private array $prices = [];

    public function add(int $price): void
    {
        $this->prices[] = $price;
    }

    public function total(): int
    {
        return array_sum($this->prices);
    }
}

final class CartChecks extends TestCase
{
    public function testSumsThePrices(): void
    {
        $cart = new Cart();
        $cart->add(10);

        $cart->add(5);

        self::assertSame(15, $cart->total());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/testClassSuffix/Failing.php:25:Test class Docs\TestClassSuffix\Failing\CartChecks does not end with "Test": PHPUnit only collects the files whose name ends with the suffix of the <directory> that holds them, "Test.php" by default.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\TestClassSuffix\Passing;

use PHPUnit\Framework\TestCase;

final class Cart
{
    /** @var list<int> */
    private array $prices = [];

    public function add(int $price): void
    {
        $this->prices[] = $price;
    }

    public function total(): int
    {
        return array_sum($this->prices);
    }
}

final class CartTest extends TestCase
{
    public function testSumsThePrices(): void
    {
        $cart = new Cart();
        $cart->add(10);

        $cart->add(5);

        self::assertSame(15, $cart->total());
    }
}
```

## Why

PHPUnit finds tests by file name: a `<directory>` collects the files ending with `Test.php` unless the configuration says otherwise. A test class called `CartChecks` in `CartChecks.php` is skipped without a word.

- **Regression detection.** As with a file outside every suite, the test exists, is maintained, and never runs. `testFileInTestsuite` catches it when a configuration is found; this rule catches it from the class name alone, with no configuration to read.
- **Navigation.** `CartTest` next to `Cart` is the convention every PHP tool and IDE relies on to jump between a class and its test.
- **Consistency.** One suffix for every test class makes `tests/` greppable and the suites simple to configure.

Source practice: PHPUnit's default `suffix="Test.php"`; the same check exists as `TestCaseWithSuffixRule` in [ergebnis/phpstan-rules](https://github.com/ergebnis/phpstan-rules).

## Known limits

- The rule checks the class name, not the file name: a `CartTest` class in `Cart.php` passes here and is caught by `testFileInTestsuite`.
- A project that configures another suffix in `phpunit.xml` must set the same one in `testClassSuffix.suffix`.

## Ignoring

Use the identifier `testPractices.testClassSuffix`, on the line:

```php
// @phpstan-ignore testPractices.testClassSuffix
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.testClassSuffix
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/testClassSuffix/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
