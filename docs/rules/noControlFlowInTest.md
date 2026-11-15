# `testPractices.noControlFlowInTest`

No control flow in a test. Enabled from `testPractices.level` **1** (see [levels](../../README.md#levels)).

Auto-fixable: no. Unrolling a loop or splitting a branch into separate tests is a design choice.

Related skill: none: no section of the [marketplace skills](https://github.com/FabienSalles/claude-marketplace) states this practice yet.

## What it detects

An `if`, `for`, `foreach`, `while`, `do`/`while` or `switch` in a test method body, and a `match` whose arms call something, assign or throw (a `match` that only selects a value stays allowed). Closures, arrow functions and anonymous classes passed from the test (an `array_map()` callback, `Argument::that()`) are not inspected, and neither are helper methods.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noControlFlowInTest: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoControlFlowInTest\Failing;

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
    public function testSumsEveryPrice(): void
    {
        $cart = new Cart();

        foreach ([10, 5, 20] as $price) {
            $cart->add($price);
        }

        self::assertSame(35, $cart->total());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noControlFlowInTest/Failing.php:31:Test testSumsEveryPrice() contains a foreach: a test runs one straight path, otherwise some of its branches are never checked.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoControlFlowInTest\Passing;

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
    public function testSumsEveryPrice(): void
    {
        $cart = new Cart();
        $cart->add(10);
        $cart->add(5);

        $cart->add(20);

        self::assertSame(35, $cart->total());
    }
}
```

## Why

A test should read top to bottom, one line after the other, and run the same path every time:

- **Untested branches.** An `if` in a test means some runs check something and others do not: when the condition is false, its assertions silently do not run. A loop over an empty list asserts nothing.
- **Logic that needs its own test.** A loop that builds the input or the expectation is code; when it is wrong, the test is wrong in a way nothing reports ([Testing on the Toilet: Don't Put Logic in Tests](https://testing.googleblog.com/2014/07/testing-on-toilet-dont-put-logic-in.html), and xUnit Test Patterns, [Conditional Test Logic](http://xunitpatterns.com/Conditional%20Test%20Logic.html)).
- **Diagnosis.** One case per test, or one data provider row per case, fails with the name of the case. A loop fails "at iteration 3", if it says anything.

Source practice: linear tests; a data provider for several cases.

## Known limits

- A loop that only builds a large fixture is reported too; move it to a helper or a factory.
- A `match` arm that calls a pure function is treated as a side effect.

## Ignoring

Use the identifier `testPractices.noControlFlowInTest`, on the line:

```php
// @phpstan-ignore testPractices.noControlFlowInTest
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noControlFlowInTest
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noControlFlowInTest/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
