# `testPractices.structuredAssertion`

Structured assertion. Enabled from `testPractices.level` **2** (see [levels](../../README.md#levels)).

Auto-fixable: no. Building the expected object or array needs to know its constructor or its shape.

Related skill: [testing-principles › 9. Structured Assertions Over Property-By-Property](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#9-structured-assertions-over-property-by-property), [php-test-conventions › assertion-patterns › assertEquals with Expected Object](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/references/assertion-patterns.md#assertequals-with-expected-object).

## What it detects

In a test with exactly two assertions, two consecutive `assertSame()`, `assertEquals()`, `assertCount()`, `assertTrue()`, `assertFalse()` or `assertNull()` whose actual value is a field of the same variable: a property (`$customer->name`), a getter without argument (`$customer->isVip()`) or an array key (`$payload['status']`). From three assertions on, `assertionCount` takes over.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            structuredAssertion: error
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\StructuredAssertion\Failing;

use PHPUnit\Framework\TestCase;

final readonly class Customer
{
    public function __construct(public string $name, public string $email)
    {
    }
}

final class CustomerFactory
{
    public function fromSignup(string $name, string $email): Customer
    {
        return new Customer(ucfirst($name), strtolower($email));
    }
}

final class CustomerFactoryTest extends TestCase
{
    public function testNormalisesTheSignup(): void
    {
        $customer = (new CustomerFactory())->fromSignup('john', 'John@Example.com');

        self::assertSame('John', $customer->name);
        self::assertSame('john@example.com', $customer->email);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/structuredAssertion/Failing.php:31:Test testNormalisesTheSignup() checks $customer field by field: one assertion on the whole value shows every difference at once.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\StructuredAssertion\Passing;

use PHPUnit\Framework\TestCase;

final readonly class Customer
{
    public function __construct(public string $name, public string $email)
    {
    }
}

final class CustomerFactory
{
    public function fromSignup(string $name, string $email): Customer
    {
        return new Customer(ucfirst($name), strtolower($email));
    }
}

final class CustomerFactoryTest extends TestCase
{
    public function testNormalisesTheSignup(): void
    {
        $customer = (new CustomerFactory())->fromSignup('john', 'John@Example.com');

        self::assertEquals(new Customer('John', 'john@example.com'), $customer);
    }
}
```

## Why

Two assertions on two fields of the same object are one expectation split in two:

- **Complete diff.** PHPUnit stops at the first failure ([Assertion Roulette](http://xunitpatterns.com/Assertion%20Roulette.html)). If both the name and the email are wrong, the field-by-field test shows the name only; `assertEquals(new Customer('John', 'john@example.com'), $customer)` shows both, side by side.
- **Completeness.** The object assertion also fails on a field nobody thought of checking; the field-by-field one silently ignores it.
- **Readability.** The expected value reads as the specification of the result, in one expression.

Source practice: one structured assertion per test.

## Known limits

- Reported as a warning: two assertions are still allowed by `assertionCount`.
- Fields of two different variables are not grouped, even when they belong together.

## Ignoring

Use the identifier `testPractices.structuredAssertion`, on the line:

```php
// @phpstan-ignore testPractices.structuredAssertion
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.structuredAssertion
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/structuredAssertion/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
