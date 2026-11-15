# `testPractices.noGuardAssertion`

No guard assertion. Enabled from `testPractices.level` **1** (see [levels](../../README.md#levels)).

Auto-fixable: yes. `vendor/bin/phpstan analyse --fix` removes the guard statement. The printer also drops the blank line in front of the removed statement; rerun your code style fixer if it matters.

Related skill: [php-test-conventions › assertion-patterns › Avoid Guard Assertions](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/references/assertion-patterns.md#avoid-guard-assertions).

## What it detects

An assertion used as a guard before the final assertion of a test: `assertCount`, `assertInstanceOf`, `assertNotNull`, `assertNotEmpty`, `assertNotFalse`, `assertArrayHasKey`, `assertObjectHasProperty` and every `assertIs*` (`assertIsArray`, `assertIsString`...), when another assertion follows. The same assertion as the last one of the test is the real expectation and is not reported.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noGuardAssertion: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoGuardAssertion\Failing;

use PHPUnit\Framework\TestCase;

final readonly class OrderLine
{
    public function __construct(public string $sku, public int $quantity)
    {
    }
}

final class OrderLinesParser
{
    /** @return list<OrderLine> */
    public function parse(string $csv): array
    {
        return array_map(
            static fn (string $row): OrderLine => new OrderLine(...array_map(trim(...), explode(';', $row)) + [1 => 0]),
            array_filter(explode("\n", $csv)),
        );
    }
}

final class OrderLinesParserTest extends TestCase
{
    public function testParsesOneLine(): void
    {
        $lines = (new OrderLinesParser())->parse("BOOK-1;2\n");

        self::assertCount(1, $lines);
        self::assertSame('BOOK-1', $lines[0]->sku ?? null);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noGuardAssertion/Failing.php:34:Test testParsesOneLine() calls assertCount() as a guard before the final assertion. Drop the guard and let the final assertion show the full diff.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoGuardAssertion\Passing;

use PHPUnit\Framework\TestCase;

final readonly class OrderLine
{
    public function __construct(public string $sku, public int $quantity)
    {
    }
}

final class OrderLinesParser
{
    /** @return list<OrderLine> */
    public function parse(string $csv): array
    {
        return array_map(
            static fn (string $row): OrderLine => new OrderLine(...array_map(trim(...), explode(';', $row)) + [1 => 0]),
            array_filter(explode("\n", $csv)),
        );
    }
}

final class OrderLinesParserTest extends TestCase
{
    public function testParsesOneLine(): void
    {
        $lines = (new OrderLinesParser())->parse("BOOK-1;2\n");

        self::assertEquals([new OrderLine('BOOK-1', 2)], $lines);
    }
}
```

## Why

A guard checks a precondition of the real assertion: "there is one element", "it is an `OrderLine`", "it is not null". It looks careful, but it degrades the test:

- **It hides the diff.** When the result is wrong, the guard fails first with `Failed asserting that actual size 0 matches expected size 1`. The final assertion, which would have printed the whole expected and actual values side by side, never runs. The developer has to rerun the test with a `var_dump()` to see what came back.
- **It duplicates the real assertion.** A structured assertion (`assertEquals([new OrderLine('BOOK-1', 2)], $lines)`) already fails when the count, the type or the nullability is wrong, with a better message. The guard adds lines and a second reason to fail without adding any detection.
- **It exists for the type checker, not for the reader.** Most guards are there so that `$lines[0]->sku` does not crash or so that a static analyser knows the type. Reading permissively (`$lines[0] ?? null`, `?->`) keeps the final assertion as the single point of failure.

xUnit Test Patterns describes a [Guard Assertion](http://xunitpatterns.com/Guard%20Assertion.html) as a way to replace an `if` in a test. This rule does not forbid that use (an assertion that is the last one is never reported); it targets the guard placed in front of a more precise assertion, which only shortens the failure message.

Source practice: one assertion that fails with a complete diff; xUnit Test Patterns, [Assertion Roulette](http://xunitpatterns.com/Assertion%20Roulette.html).

## Known limits

- `assertCount()` or `assertInstanceOf()` before an assertion on another value (two real expectations) is reported too. Make it one structured assertion, or split the test.
- Only the PHPUnit assertions listed above are guards; a custom `assertValidOrder()` is not.

## Ignoring

Use the identifier `testPractices.noGuardAssertion`, on the line:

```php
// @phpstan-ignore testPractices.noGuardAssertion
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noGuardAssertion
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noGuardAssertion/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
