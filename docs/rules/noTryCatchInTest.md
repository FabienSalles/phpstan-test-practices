# `testPractices.noTryCatchInTest`

No try/catch in a test. Enabled from `testPractices.level` **0** (see [levels](../../README.md#levels)).

Auto-fixable: no. Turning the catch block into `expectException()` / `expectExceptionMessage()` needs to know which assertions of the catch block are the expectation.

Related skill: none: no section of the [marketplace skills](https://github.com/FabienSalles/claude-marketplace) states this practice yet.

## What it detects

A `try` with at least one `catch` in a test method body (closures and anonymous classes excluded). Two forms are allowed:

- the `try` block ends with `self::fail()`, `static::fail()` or `$this->fail()`: the test fails when nothing is thrown, and the catch block inspects the exception;
- `try`/`finally` without `catch`, for cleanup.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noTryCatchInTest: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoTryCatchInTest\Failing;

use PHPUnit\Framework\TestCase;

final class AmountParser
{
    public function parse(string $amount): int
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not an amount.', $amount));
        }

        return (int) $amount;
    }
}

final class AmountParserTest extends TestCase
{
    public function testRejectsAnythingButANumber(): void
    {
        try {
            (new AmountParser())->parse('ten');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('"ten" is not an amount.', $exception->getMessage());
        }
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noTryCatchInTest/Failing.php:25:Test testRejectsAnythingButANumber() catches an exception: when nothing is thrown, the catch block never runs and the test passes anyway.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoTryCatchInTest\Passing;

use PHPUnit\Framework\TestCase;

final class AmountParser
{
    public function parse(string $amount): int
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not an amount.', $amount));
        }

        return (int) $amount;
    }
}

final class AmountParserTest extends TestCase
{
    public function testRejectsAnythingButANumber(): void
    {
        $parser = new AmountParser();
        $this->expectExceptionObject(new \InvalidArgumentException('"ten" is not an amount.'));

        $parser->parse('ten');
    }
}
```

## Why

A test that expects an exception must fail when the exception does not come. A `try`/`catch` does the opposite:

- **It passes when nothing is thrown.** If `parse('ten')` stops throwing, the catch block is skipped, the assertion inside it never runs, and the test is green. The regression it was written for goes through.
- **PHPUnit has the tool.** `expectException()`, `expectExceptionMessage()`, `expectExceptionCode()` and `expectExceptionObject()` fail the test when the act does not throw, and report the difference when it throws something else.
- **Readability.** The expectation is declared, then the act is the last line: Arrange-Act-Assert, with no branch to follow.

When the caught exception carries data that the `expectException*` methods cannot reach (a list of violations, a domain payload), end the `try` block with `self::fail()` so the missing exception fails the test.

Source practice: exception tests through PHPUnit's expectations; [Testing on the Toilet: Don't Put Logic in Tests](https://testing.googleblog.com/2014/07/testing-on-toilet-dont-put-logic-in.html).

## Known limits

- A `try`/`catch` in a helper method called by the test is not seen.
- `self::fail()` must be the last statement of the `try` block; a `fail()` elsewhere does not exempt it.

## Ignoring

Use the identifier `testPractices.noTryCatchInTest`, on the line:

```php
// @phpstan-ignore testPractices.noTryCatchInTest
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noTryCatchInTest
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noTryCatchInTest/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
