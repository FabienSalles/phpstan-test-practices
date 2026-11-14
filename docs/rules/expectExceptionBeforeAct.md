# `testPractices.expectExceptionBeforeAct`

Nothing after the throwing act. Enabled from `testPractices.level` **0** (see [levels](../../README.md#levels)).

Auto-fixable: no. The statements after the act are assertions someone meant to run; deleting them would hide that intention instead of moving it to another test.

## What it detects

A test method whose last `expectException*()` call (`expectException`, `expectExceptionMessage`, `expectExceptionCode`, `expectExceptionObject`...) is followed by more than one top-level statement. The statement right after the expectation is the act; it throws, so everything after it is dead code.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            expectExceptionBeforeAct: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\ExpectExceptionBeforeAct\Failing;

use PHPUnit\Framework\TestCase;

final class Account
{
    private string $status = 'OPEN';

    public function close(): void
    {
        if ($this->status === 'CLOSED') {
            throw new \LogicException('The account is already closed.');
        }

        $this->status = 'CLOSED';
    }

    public function status(): string
    {
        return $this->status;
    }
}

final class AccountTest extends TestCase
{
    public function testRefusesToCloseTwice(): void
    {
        $account = new Account();
        $account->close();
        $this->expectException(\LogicException::class);

        $account->close();
        self::assertSame('CLOSED', $account->status());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/expectExceptionBeforeAct/Failing.php:37:Test testRefusesToCloseTwice() has statements after the act that follows expectException(): the act throws, so they never run.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\ExpectExceptionBeforeAct\Passing;

use PHPUnit\Framework\TestCase;

final class Account
{
    private string $status = 'OPEN';

    public function close(): void
    {
        if ($this->status === 'CLOSED') {
            throw new \LogicException('The account is already closed.');
        }

        $this->status = 'CLOSED';
    }

    public function status(): string
    {
        return $this->status;
    }
}

final class AccountTest extends TestCase
{
    public function testRefusesToCloseTwice(): void
    {
        $account = new Account();
        $account->close();
        $this->expectException(\LogicException::class);

        $account->close();
    }

    public function testClosesAnOpenAccount(): void
    {
        $account = new Account();

        $account->close();

        self::assertSame('CLOSED', $account->status());
    }
}
```

## Why

When the act throws, PHPUnit catches the exception, compares it with the expectation and ends the test. Nothing after the act runs:

- **Dead assertions.** `self::assertSame('CLOSED', $account->status())` after a throwing act is never executed, whatever its value. It reads as a check and checks nothing; PHPUnit does not warn about it.
- **Two behaviours in one test.** "Refuses to close twice" and "is closed after the first call" are two tests; each gets a name and fails on its own.
- **Arrange-Act-Assert.** With exceptions, the assertion moves before the act (the expectation) and the act is the last line. Anything after it breaks that shape.

Source practice: the act is the last statement of an exception test.

## Known limits

- Only top-level statements count; an act wrapped in a block (a closure called later) is not analysed.
- An `expectException()` placed after the act is not this rule's concern.

## Ignoring

Use the identifier `testPractices.expectExceptionBeforeAct`, on the line:

```php
// @phpstan-ignore testPractices.expectExceptionBeforeAct
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.expectExceptionBeforeAct
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/expectExceptionBeforeAct/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
