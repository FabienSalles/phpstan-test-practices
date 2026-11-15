# `testPractices.frozenClock`

Frozen clock. Enabled from `testPractices.level` **1** (see [levels](../../README.md#levels)).

Auto-fixable: no. The absolute date to use, and how to inject a clock into the code under test, are choices of the test author.

Related skill: [testing-principles › Freeze the Clock](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#freeze-the-clock).

## What it detects

A read of the system clock in any method of a test class (tests, helpers, data providers, `setUp()`):

- `new \DateTime()` / `new \DateTimeImmutable()` without argument or with a relative string (`'now'`, `'tomorrow'`, `'+29 days'`...); an absolute date (`'2026-11-12 09:00'`, `'@1700000000'`) passes;
- `time()`, `microtime()`, `hrtime()`, `now()`, `date()` with one argument, `mktime()` without all its arguments, `strtotime()` with a relative string;
- `Carbon::now()`, `CarbonImmutable::now()`, `Symfony\Component\Clock\Clock::get()`.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            frozenClock: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\FrozenClock\Failing;

use PHPUnit\Framework\TestCase;

final class Subscription
{
    private ?\DateTimeImmutable $endsAt = null;

    public function start(\DateTimeImmutable $now): void
    {
        $this->endsAt = $now->modify('+30 days');
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return $this->endsAt !== null && $now < $this->endsAt;
    }
}

final class SubscriptionTest extends TestCase
{
    public function testIsActiveRightAfterTheStart(): void
    {
        $subscription = new Subscription();
        $subscription->start(new \DateTimeImmutable());

        $active = $subscription->isActive(new \DateTimeImmutable('+29 days'));

        self::assertTrue($active);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/frozenClock/Failing.php:29:testIsActiveRightAfterTheStart() reads the system clock: the test depends on when it runs.
tests/Fixtures/Docs/frozenClock/Failing.php:31:testIsActiveRightAfterTheStart() reads the system clock: the test depends on when it runs.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\FrozenClock\Passing;

use PHPUnit\Framework\TestCase;

final class Subscription
{
    private ?\DateTimeImmutable $endsAt = null;

    public function start(\DateTimeImmutable $now): void
    {
        $this->endsAt = $now->modify('+30 days');
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return $this->endsAt !== null && $now < $this->endsAt;
    }
}

final class SubscriptionTest extends TestCase
{
    public function testIsActiveRightAfterTheStart(): void
    {
        $subscription = new Subscription();
        $subscription->start(new \DateTimeImmutable('2026-11-01 09:00'));

        $active = $subscription->isActive(new \DateTimeImmutable('2026-11-30 09:00'));

        self::assertTrue($active);
    }
}
```

## Why

A unit test must give the same result every time it runs (FIRST: Repeatable):

- **Flaky tests.** A test built on "now" passes all month and fails on the 31st, at midnight, on a daylight saving change or on a slow CI runner. Nobody changed the code; the date did.
- **Unreadable expectations.** With a moving date, the expected value has to be computed from "now" too, which is the computed-expectation problem again. With `2026-11-01 09:00` in, the reader sees `2026-12-01 09:00` out.
- **Design pressure.** Code that calls `new \DateTimeImmutable()` deep inside cannot be tested without the real clock. Injecting a clock (`Psr\Clock\ClockInterface`, Symfony's `MockClock`) makes time an input like any other.

Source practice: freeze the inputs (clock, randomness, identifiers); Michael Feathers, a unit test does not touch the system clock.

## Known limits

- A date read to fill a field the test never asserts (an integration test inserting `createdAt`) is reported too; ignore it with the reason.
- A clock read inside the code under test is not this rule's concern.

## Ignoring

Use the identifier `testPractices.frozenClock`, on the line:

```php
// @phpstan-ignore testPractices.frozenClock
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.frozenClock
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/frozenClock/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
