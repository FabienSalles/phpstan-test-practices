# `testPractices.exceptionTestNamesException`

Exception test names the exception. Enabled from `testPractices.level` **2** (see [levels](../../README.md#levels)).

Auto-fixable: no. The new name has to keep the condition part of the old one, which a fix cannot guess.

Related skill: [php-test-conventions › PHP-specific: Exception Test Naming](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/SKILL.md#php-specific-exception-test-naming).

## What it detects

A test calling `expectException(X::class)` whose name does not contain the short class name `X`, compared case-insensitively. The name may also drop an `Exception` suffix and a `Cannot` prefix (`CannotSuspendBeneficiaryException` matches `...SuspendBeneficiary...`). When the argument is not a class constant (an exception class coming from a data provider), the rule does not apply.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            exceptionTestNamesException: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\ExceptionTestNamesException\Failing;

use PHPUnit\Framework\TestCase;

final class MissingBirthDate extends \DomainException
{
}

final readonly class Buyer
{
    public function __construct(public ?string $birthDate)
    {
        if ($birthDate === null) {
            throw new MissingBirthDate('A buyer needs a birth date.');
        }
    }
}

final class BuyerTest extends TestCase
{
    public function testRejectsAnIncompleteBuyer(): void
    {
        $this->expectException(MissingBirthDate::class);

        new Buyer(null);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/exceptionTestNamesException/Failing.php:27:Test testRejectsAnIncompleteBuyer() expects MissingBirthDate but its name does not say so: the name should tell which error the test is about.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\ExceptionTestNamesException\Passing;

use PHPUnit\Framework\TestCase;

final class MissingBirthDate extends \DomainException
{
}

final readonly class Buyer
{
    public function __construct(public ?string $birthDate)
    {
        if ($birthDate === null) {
            throw new MissingBirthDate('A buyer needs a birth date.');
        }
    }
}

final class BuyerTest extends TestCase
{
    public function testThrowsMissingBirthDateWhenTheBirthDateIsAbsent(): void
    {
        $this->expectException(MissingBirthDate::class);

        new Buyer(null);
    }
}
```

## Why

An exception test is about one specific error:

- **Diagnosis.** In a test report, `testThrowsMissingBirthDateWhenTheBirthDateIsAbsent` says which error was expected and when. `testRejectsAnIncompleteBuyer` says neither, and a reader has to open the test.
- **Precision.** Naming the exception keeps the author honest: a test that only checks "something was thrown" (`\Exception`) is a weak test, and naming it makes that visible.
- **Living documentation.** The list of test names of a class becomes the list of errors it can raise and under which conditions.

Source practice: `itThrows{Exception}When{Condition}`.

## Known limits

- Very long exception names make long test names; the accepted short forms help, otherwise ignore the line.
- `expectExceptionObject()` and `expectExceptionMessage()` are not checked.

## Ignoring

Use the identifier `testPractices.exceptionTestNamesException`, on the line:

```php
// @phpstan-ignore testPractices.exceptionTestNamesException
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.exceptionTestNamesException
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/exceptionTestNamesException/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
