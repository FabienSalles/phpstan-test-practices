# `testPractices.testMethodIsDiscoverable`

Test method is discoverable. Enabled from `testPractices.level` **0** (see [levels](../../README.md#levels)).

Auto-fixable: no. The finding is computed from collected data across files, and PHPStan attaches a fix only to the AST node of a rule that runs on that node. Add `#[Test]` (PHPUnit 10 and later) or the `test` prefix by hand.

## What it detects

A method of a test class (or of its parents and traits) that contains assertions but that PHPUnit never runs: it has no `test` prefix, no `#[Test]` attribute and no `@test` annotation, and no test method reaches it.

A method is reached when a test method, a hook (`setUp()`, `tearDown()`, `setUpBeforeClass()`, `tearDownAfterClass()`, `assertPreConditions()`, `assertPostConditions()`, or a method with `#[Before]`, `#[After]`, `#[BeforeClass]`, `#[AfterClass]`, `#[PreCondition]`, `#[PostCondition]` or the matching annotation) calls it through `$this->`, `self::` or `static::`, passes it as `[$this, 'name']`, or names it as its `#[DataProvider]`/`@dataProvider`, directly or through other methods. The search spans the concrete class, its parents and its traits, through PHPStan collectors.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            testMethodIsDiscoverable: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\TestMethodIsDiscoverable\Failing;

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
    public function sumsThePrices(): void
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
tests/Fixtures/Docs/testMethodIsDiscoverable/Failing.php:27:Method sumsThePrices() makes assertions but PHPUnit never runs it: it is not a test (test prefix, #[Test] or @test) and no test calls it.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\TestMethodIsDiscoverable\Passing;

use PHPUnit\Framework\Attributes\Test;
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
    #[Test]
    public function sumsThePrices(): void
    {
        $cart = new Cart();
        $cart->add(10);

        $cart->add(5);

        $this->assertTotal(15, $cart);
    }

    private function assertTotal(int $expected, Cart $cart): void
    {
        self::assertSame($expected, $cart->total());
    }
}
```

## Why

A public method full of assertions looks like a test to a reviewer. PHPUnit disagrees: without the prefix or a marker, it is a plain method, and nothing calls it.

- **Regression detection.** The assertions were written to catch a regression and never execute. The suite stays green whatever the code does, and the missing test only shows up when someone wonders why a bug was not caught.
- **It is an easy mistake.** Renaming `testSumsThePrices()` to the sentence style `sumsThePrices()` without adding `#[Test]`, or moving PHPUnit 9 annotations to PHPUnit 12 (which no longer reads `@test`), silently disables tests.
- **Helpers stay allowed.** An assertion helper such as `assertTotal()` is fine as long as a test calls it; only the methods no test can reach are reported.

Source practice: every test must be discoverable by the runner; PHPUnit's `beStrictAboutTestsThatDoNotTestAnything` only reports tests that run, not methods that never run.

## Known limits

- A method called dynamically (`$this->$name()`, `call_user_func('...')`) is not followed and may be reported.
- `@test` still counts as a marker, although PHPUnit 12 no longer reads annotations.
- Only the analysed paths are read: a parent class outside them contributes no callers.

## Ignoring

Use the identifier `testPractices.testMethodIsDiscoverable`, on the line:

```php
// @phpstan-ignore testPractices.testMethodIsDiscoverable
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.testMethodIsDiscoverable
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/testMethodIsDiscoverable/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
