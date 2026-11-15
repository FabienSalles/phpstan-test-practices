# `testPractices.noRandomness`

No randomness. Enabled from `testPractices.level` **1** (see [levels](../../README.md#levels)).

Auto-fixable: no. The fixed value to use instead depends on what the test means.

Related skill: [testing-principles › No Randomness](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#no-randomness).

## What it detects

In any method of a test class: `rand()`, `mt_rand()`, `random_int()`, `random_bytes()`, `lcg_value()`, `uniqid()`, `shuffle()`, `array_rand()`, `str_shuffle()`, `Ramsey\Uuid\Uuid::uuid4()`, `Symfony\Component\Uid\Uuid::v4()` / `v7()`, `Ulid::generate()`, and `Faker\Factory::create()`.

A Faker instance stored in a variable and seeded in the same method (`$faker->seed(1234)`) is tolerated: its sequence is repeatable.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noRandomness: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoRandomness\Failing;

use PHPUnit\Framework\TestCase;

final class OrderLabel
{
    public function for(string $reference): string
    {
        return sprintf('Order %s', strtoupper($reference));
    }
}

final class OrderLabelTest extends TestCase
{
    public function testUppercasesTheReference(): void
    {
        $reference = uniqid('ord-');

        $label = (new OrderLabel())->for($reference);

        self::assertStringStartsWith('Order ORD-', $label);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noRandomness/Failing.php:21:testUppercasesTheReference() draws a random value with uniqid(): the test may behave differently on every run.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoRandomness\Passing;

use PHPUnit\Framework\TestCase;

final class OrderLabel
{
    public function for(string $reference): string
    {
        return sprintf('Order %s', strtoupper($reference));
    }
}

final class OrderLabelTest extends TestCase
{
    public function testUppercasesTheReference(): void
    {
        $label = (new OrderLabel())->for('ord-42');

        self::assertSame('Order ORD-42', $label);
    }
}
```

## Why

A test must give the same result on every run (FIRST: Repeatable):

- **Flaky tests.** A random amount, identifier or name explores a different case on each run. The failure that shows up once in fifty runs cannot be reproduced, and the case that would have failed may never be drawn.
- **Unreadable expectations.** With a random input, the expectation has to be loose (`assertStringStartsWith`) or computed from the input, and the test stops saying which output the code must produce.
- **Debugging.** A failing run tells which assertion broke, not which values led there. A literal input is in the test; a random one is gone.

When generated data helps (many fields nobody cares about), seed the generator so the data is the same every time. Property-based testing is a different, deliberate technique, with a tool that records and shrinks the failing case.

Source practice: freeze the inputs (clock, randomness, identifiers).

## Known limits

- A random value that never reaches an assertion (a temporary file name) is reported too; ignore the line with the reason.
- Randomness inside the code under test is not this rule's concern; inject its generator to control it.

## Ignoring

Use the identifier `testPractices.noRandomness`, on the line:

```php
// @phpstan-ignore testPractices.noRandomness
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noRandomness
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noRandomness/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
