# `testPractices.assertionsLast`

Assertions last. Enabled from `testPractices.level` **0** (see [levels](../../README.md#levels)).

Auto-fixable: no. Moving a statement before the assertions can change what the test checks; splitting the test needs a name and an intention only a human knows.

Related skill: [testing-principles › 5. AAA / GWT Pattern](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#5-aaa--gwt-pattern).

## What it detects

Every top-level statement of a test method that comes after its first assertion, and any assertion hidden in a closure, an arrow function or an anonymous class of the arrange (a `willReturnCallback()`, a custom double...). An assertion inside `Argument::that()` within a Prophecy spy verification belongs to the final block and stays allowed.

An assertion is an `assert*` call through `self::`, `static::`, `SomeClass::` or `$this->`, or a Prophecy spy verification (`shouldHaveBeenCalled()`, `shouldHaveBeenCalledTimes()`, `shouldHaveBeenCalledOnce()`, `shouldNotHaveBeenCalled()`, `shouldHave()`).

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            assertionsLast: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\AssertionsLast\Failing;

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
    public function testAddsPricesToTheTotal(): void
    {
        $cart = new Cart();
        $cart->add(10);
        self::assertSame(10, $cart->total());
        $cart->add(5);
        self::assertSame(15, $cart->total());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/assertionsLast/Failing.php:32:Test testAddsPricesToTheTotal() has a statement after an assertion. Assertions come last, in a single final block.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\AssertionsLast\Passing;

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
    public function testAddsPricesToTheTotal(): void
    {
        $cart = new Cart();
        $cart->add(10);

        $cart->add(5);

        self::assertSame(15, $cart->total());
    }
}
```

## Why

A test tells one short story: arrange the world, act once, assert the outcome ([Arrange-Act-Assert](https://xp123.com/3a-arrange-act-assert/), Bill Wake). When assertions are interleaved with actions, the test checks several states of the object one after the other:

- **Readability.** The reader has to replay the scenario step by step to know which state each assertion is about. With a single final block, the act is the line just above the assertions and the expectation reads as the result of that act.
- **Failure diagnosis.** PHPUnit stops at the first failing assertion. An early assertion failing hides whether the later steps work, and its message talks about an intermediate state nobody asked about.
- **One behaviour per test.** Two act/assert pairs are two behaviours. Split, each test gets a name that says what broke, and a regression shows up as one red test instead of a test that fails "somewhere in the middle".
- **Assertions in the arrange lie about their timing.** An assertion inside a `willReturnCallback()` runs during the act, can be swallowed by a `try`/`catch` in the code under test, and does not run at all when the call never happens: the test then passes without checking anything. Capturing the value (a spy, a hand-written double) and asserting after the act makes the check unconditional.

Source practice: Arrange-Act-Assert; xUnit Test Patterns, [Obscure Test](http://xunitpatterns.com/Obscure%20Test.html) (Gerard Meszaros).

## Known limits

- A statement after the assertions that is not part of the scenario (a cleanup, a `restore_error_handler()`) is reported too: move it to `tearDown()`.
- Assertions written through a custom helper that does not start with `assert` are not recognised as assertions.

## Ignoring

Use the identifier `testPractices.assertionsLast`, on the line:

```php
// @phpstan-ignore testPractices.assertionsLast
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.assertionsLast
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/assertionsLast/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
