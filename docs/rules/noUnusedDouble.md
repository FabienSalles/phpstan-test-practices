# `testPractices.noUnusedDouble`

No unused double. Enabled from `testPractices.level` **1** (see [levels](../../README.md#levels)).

Auto-fixable: no. Removing the assignment is mechanical, but an unused double is as often a forgotten argument of the code under test as a leftover.

Related skill: none: no section of the [marketplace skills](https://github.com/FabienSalles/claude-marketplace) states this practice yet.

## What it detects

A variable of a test method assigned from a double factory (`createMock()`, `createStub()`, `prophesize()`, `getMockBuilder()...->getMock()`...) or from an anonymous class, and never read afterwards: not passed as an argument, not called, not captured by a closure.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noUnusedDouble: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoUnusedDouble\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface Mailer
{
    public function send(string $to): void;
}

interface Clock
{
    public function now(): \DateTimeImmutable;
}

final readonly class Welcome
{
    public function __construct(private Mailer $mailer)
    {
    }

    public function welcome(string $email): void
    {
        $this->mailer->send($email);
    }
}

final class WelcomeTest extends TestCase
{
    use ProphecyTrait;

    public function testEmailsTheNewUser(): void
    {
        $mailer = $this->prophesize(Mailer::class);
        $clock = $this->createStub(Clock::class);

        (new Welcome($mailer->reveal()))->welcome('alice@example.com');

        $mailer->send('alice@example.com')->shouldHaveBeenCalled();
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noUnusedDouble/Failing.php:39:Test testEmailsTheNewUser() creates the double $clock and never uses it.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoUnusedDouble\Passing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface Mailer
{
    public function send(string $to): void;
}

interface Clock
{
    public function now(): \DateTimeImmutable;
}

final readonly class Welcome
{
    public function __construct(private Mailer $mailer)
    {
    }

    public function welcome(string $email): void
    {
        $this->mailer->send($email);
    }
}

final class WelcomeTest extends TestCase
{
    use ProphecyTrait;

    public function testEmailsTheNewUser(): void
    {
        $mailer = $this->prophesize(Mailer::class);

        (new Welcome($mailer->reveal()))->welcome('alice@example.com');

        $mailer->send('alice@example.com')->shouldHaveBeenCalled();
    }
}
```

## Why

Every line of the arrange should matter to the scenario:

- **Noise.** A double that nothing uses makes the reader look for its role. The test reads as if the clock mattered, and it does not.
- **A sign of a missing act.** Often the double was meant to be passed to the code under test and was forgotten, so the code runs with a default collaborator and the test checks something else than intended.
- **Copy-paste debt.** Unused doubles spread when tests are copied; each copy carries the confusion along.

Source practice: a test contains only what its scenario needs.

## Known limits

- A double read only through `$double->reveal()` whose result is discarded counts as used.
- Doubles stored in properties are not checked.

## Ignoring

Use the identifier `testPractices.noUnusedDouble`, on the line:

```php
// @phpstan-ignore testPractices.noUnusedDouble
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noUnusedDouble
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noUnusedDouble/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
