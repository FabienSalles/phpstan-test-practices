# `testPractices.noFixtureInSetUp`

No fixture in setUp(). Enabled from `testPractices.level` **3** (see [levels](../../README.md#levels)).

Auto-fixable: no. Moving a shared object into each test that uses it needs to know which tests use it.

Related skill: [testing-principles › 4. DAMP Over DRY](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#4-damp-over-dry).

## What it detects

An assignment to a property (`$this->x = ...` or `self::$x = ...`) in `setUp()`, `setUpBeforeClass()` or a method carrying `#[Before]` or `#[BeforeClass]`, when the value is a test double (`createMock()`, `createStub()`, `prophesize()`, `getMockBuilder()`... including a chain built on them) or an object created with `new`. Calls such as `static::bootKernel()`, `static::createClient()` or `static::getContainer()` are not reported: they prepare the environment, not the scenario.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noFixtureInSetUp: error
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoFixtureInSetUp\Failing;

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
    private Cart $cart;

    protected function setUp(): void
    {
        $this->cart = new Cart();
        $this->cart->add(10);
    }

    public function testAddsAPrice(): void
    {
        $this->cart->add(5);

        self::assertSame(15, $this->cart->total());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/noFixtureInSetUp/Failing.php:31:setUp() creates a fixture shared by every test of the class: the scenario of each test is hidden in setUp().
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoFixtureInSetUp\Passing;

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
    public function testAddsAPrice(): void
    {
        $cart = new Cart();
        $cart->add(10);

        $cart->add(5);

        self::assertSame(15, $cart->total());
    }
}
```

## Why

`setUp()` runs before every test of the class. Whatever it builds is part of every scenario, but none of the tests shows it:

- **Readability.** To understand `testAddsAPrice()`, the reader has to scroll up to `setUp()` to learn that the cart already holds 10. The arrange of a test should be in the test, so that the test can be read alone, top to bottom ([Obscure Test](http://xunitpatterns.com/Obscure%20Test.html), the Mystery Guest form).
- **Coupling between tests.** Every test now depends on the same fixture. Changing it for one test changes the others, and a test that needs a slightly different object starts by undoing what `setUp()` did (xUnit Test Patterns calls it a General Fixture).
- **Unused setup.** Doubles created in `setUp()` are created for tests that never use them, and verifications attached to them run for every test.

`setUp()` stays the right place for the environment: booting the kernel, a frozen clock shared by design, a database transaction. Repeated arrange code goes into a helper or a factory that each test calls explicitly.

Source practice: setUp() for the environment, not for fixtures.

## Known limits

- Reported as a warning: a trivial shared object is sometimes acceptable.
- An object returned by a factory call (`OrderMother::paid()`) is not reported; only `new` and doubles are.

## Ignoring

Use the identifier `testPractices.noFixtureInSetUp`, on the line:

```php
// @phpstan-ignore testPractices.noFixtureInSetUp
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noFixtureInSetUp
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noFixtureInSetUp/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
