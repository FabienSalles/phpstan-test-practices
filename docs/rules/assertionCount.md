# `testPractices.assertionCount`

Assertion count. Enabled from `testPractices.level` **2** (see [levels](../../README.md#levels)).

## What it detects

The number of assertions in a test method, anywhere in its body. An assertion nested in another one (an `Argument::that()` inside a spy verification) is not counted again. 1 is preferred, 2 is allowed, `warningFrom` (3) is a warning and `errorFrom` (4) an error.

## Severity

Default severity: 3 assertions are a warning, 4 or more an error. Change the thresholds with `assertionCount.warningFrom` and `assertionCount.errorFrom`:

```neon
parameters:
    testPractices:
        assertionCount:
            warningFrom: 3
            errorFrom: 4
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\AssertionCount\Failing;

use PHPUnit\Framework\TestCase;

final readonly class Customer
{
    public function __construct(public string $name, public string $email, public string $country, public bool $vip)
    {
    }
}

final class CustomerFactory
{
    public function fromSignup(string $name, string $email): Customer
    {
        return new Customer(ucfirst($name), strtolower($email), 'FR', false);
    }
}

final class CustomerFactoryTest extends TestCase
{
    public function testCreatesACustomerFromASignup(): void
    {
        $customer = (new CustomerFactory())->fromSignup('alice', 'Alice@Example.com');

        self::assertSame('Alice', $customer->name);
        self::assertSame('alice@example.com', $customer->email);
        self::assertSame('FR', $customer->country);
        self::assertFalse($customer->vip);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/assertionCount/Failing.php:26:Test testCreatesACustomerFromASignup() makes 4 assertions. Prefer one structured assertion, or split the test by intention.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\AssertionCount\Passing;

use PHPUnit\Framework\TestCase;

final readonly class Customer
{
    public function __construct(public string $name, public string $email, public string $country, public bool $vip)
    {
    }
}

final class CustomerFactory
{
    public function fromSignup(string $name, string $email): Customer
    {
        return new Customer(ucfirst($name), strtolower($email), 'FR', false);
    }
}

final class CustomerFactoryTest extends TestCase
{
    public function testCreatesACustomerFromASignup(): void
    {
        $customer = (new CustomerFactory())->fromSignup('alice', 'Alice@Example.com');

        self::assertEquals(new Customer('Alice', 'alice@example.com', 'FR', false), $customer);
    }
}
```

## Why

Many assertions in a row are usually several checks of one object, or several behaviours in one test:

- **Complete failure messages.** PHPUnit stops at the first failing assertion ([Assertion Roulette](http://xunitpatterns.com/Assertion%20Roulette.html)): with four `assertSame()` on four fields, a broken factory shows the first wrong field only. One `assertEquals()` on the whole object, or one `assertSame()` on an array of fields, shows every difference at once.
- **One intention per test.** When the assertions are about different things, the test has several reasons to fail and a name that cannot describe all of them. The count is a cheap signal that the test should be split.
- **Readability.** One structured assertion is the specification of the expected result in a single expression, close to the act.

Source practice: one structured assertion per test; xUnit Test Patterns, Eager Test.

## Known limits

- The thresholds are a heuristic. Tune them with `assertionCount.warningFrom` and `assertionCount.errorFrom`, or ignore a specific test.
- Assertions made in a helper method are not counted.

## Ignoring

Use the identifier `testPractices.assertionCount`, on the line:

```php
// @phpstan-ignore testPractices.assertionCount
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.assertionCount
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/assertionCount/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
