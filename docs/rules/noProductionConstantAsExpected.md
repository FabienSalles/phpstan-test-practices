# `testPractices.noProductionConstantAsExpected`

No production constant as expected value. Enabled from `testPractices.level` **1** (see [levels](../../README.md#levels)).

## What it detects

A class constant of production code used as the **expected** argument of an assertion: the argument of an assertion method whose first parameter is named `$expected…` (`assertSame`, `assertEquals`, `assertNotSame`, `assertNotEquals`, `assertEqualsCanonicalizing`, `assertCount`, `assertJsonStringEqualsJsonString`...), passed positionally or as a named argument. The method is resolved through reflection on the test class, so the list follows the installed PHPUnit.

Not reported: a constant anywhere else (building a fixture or a stub, calling the code under test, a data provider, the actual value), enum cases, constants of the test class itself, constants of built-in classes, and `Foo::class`.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noProductionConstantAsExpected: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoProductionConstantAsExpected\Failing;

use PHPUnit\Framework\TestCase;

final class Order
{
    public const STATUS_CONFIRMED = 'CONFIRMED';

    private string $status = 'PENDING';

    public function confirm(): void
    {
        $this->status = self::STATUS_CONFIRMED;
    }

    public function status(): string
    {
        return $this->status;
    }
}

final class OrderTest extends TestCase
{
    public function testConfirmsTheOrder(): void
    {
        $order = new Order();

        $order->confirm();

        self::assertSame(Order::STATUS_CONFIRMED, $order->status());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noProductionConstantAsExpected/Failing.php:34:Expected value Docs\NoProductionConstantAsExpected\Failing\Order::STATUS_CONFIRMED is a production constant: the assertion compares the constant with itself. Write the literal value instead.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoProductionConstantAsExpected\Passing;

use PHPUnit\Framework\TestCase;

final class Order
{
    public const STATUS_CONFIRMED = 'CONFIRMED';

    private string $status = 'PENDING';

    public function confirm(): void
    {
        $this->status = self::STATUS_CONFIRMED;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}

final class OrderTest extends TestCase
{
    public function testConfirmsTheOrder(): void
    {
        $order = new Order();

        $order->confirm();

        self::assertSame('CONFIRMED', $order->status());
    }

    public function testRecognisesAConfirmedStatus(): void
    {
        $order = new Order();
        $order->confirm();

        $confirmed = $order->isConfirmed();

        self::assertTrue($confirmed);
    }
}
```

## Why

`assertSame(Order::STATUS_CONFIRMED, $order->status())` compares the constant with what the code returns, and the code returns the constant:

- **It does not detect a changed value.** If someone changes `STATUS_CONFIRMED` from `'CONFIRMED'` to `'CONFIRMD'`, every consumer outside the code base (the database, an API client, a message consumer) breaks, and the test stays green. The literal `'CONFIRMED'` in the test is what pins the contract.
- **It is a tautology.** The test proves that the code uses its own constant, not that it produces the value the specification asks for.
- **It reads as indirection.** The reader of a failing test has to open the production class to know what was expected.

Using the constant to drive the scenario stays fine: `new Order(Order::STATUS_CONFIRMED)` or `isFinal(Order::STATUS_CONFIRMED)` only selects an input; the expectation that follows is still written as a literal.

Source practice: literal expected values, tests as an executable specification.

## Known limits

- When the constant itself is the contract (a public default exposed on purpose), keep it and ignore the line: `// @phpstan-ignore testPractices.noProductionConstantAsExpected`.
- A constant nested inside an expected array (`['status' => Order::STATUS_CONFIRMED]`) or assigned to a variable first is not reported.
- The default severity is `error`; whether it should be a warning is still an open decision, hence the severity parameter.

## Ignoring

Use the identifier `testPractices.noProductionConstantAsExpected`, on the line:

```php
// @phpstan-ignore testPractices.noProductionConstantAsExpected
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noProductionConstantAsExpected
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noProductionConstantAsExpected/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
