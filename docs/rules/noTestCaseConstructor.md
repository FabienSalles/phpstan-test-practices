# `testPractices.noTestCaseConstructor`

No test case constructor. Enabled from `testPractices.level` **1** (see [levels](../../README.md#levels)).

Auto-fixable: no. What the constructor builds has to move into each test or into setUp(), depending on what it is.

Related skill: [php-test-conventions › PHP-specific: No TestCase Constructor](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/SKILL.md#php-specific-no-testcase-constructor).

## What it detects

A `__construct()` declared in a `TestCase` subclass.

PHPUnit 12 declares `TestCase::__construct()` final, so the override is already a fatal error there; this rule matters on PHPUnit 11, where it is silently accepted. The idea comes from `DisallowSetupAndConstructorRule` of [ikvasnica/phpstan-clean-test](https://github.com/ikvasnica/phpstan-clean-test), which is not installable with PHPStan 2.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noTestCaseConstructor: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoTestCaseConstructor\Failing;

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

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->cart = new Cart();
    }

    public function testAddsAPrice(): void
    {
        $this->cart->add(5);

        self::assertSame(5, $this->cart->total());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noTestCaseConstructor/Failing.php:29:A test case declares a constructor: PHPUnit creates one instance per test with its own arguments, and the constructor runs for every test, before setUp().
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoTestCaseConstructor\Passing;

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

        $cart->add(5);

        self::assertSame(5, $cart->total());
    }
}
```

## Why

The constructor of a test case is PHPUnit's, not the test's:

- **Lifecycle.** PHPUnit instantiates the class once per test method and data set, with its own constructor arguments, long before running it. Work done there runs for every test, at collection time, outside `setUp()` and `tearDown()`, and its failures are reported as errors of the whole class.
- **Hidden fixtures.** Like `setUp()`, but worse, a constructor hides the scenario from the tests and shares it between them.
- **Fragility.** The constructor signature is internal to PHPUnit; overriding it ties the suite to a PHPUnit version (it became final in PHPUnit 12).

Source practice: build what a test needs in its arrange, the environment in `setUp()`.

## Known limits

- None known.

## Ignoring

Use the identifier `testPractices.noTestCaseConstructor`, on the line:

```php
// @phpstan-ignore testPractices.noTestCaseConstructor
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noTestCaseConstructor
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noTestCaseConstructor/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
