# `testPractices.noDependsBetweenTests`

No dependency between tests. Enabled from `testPractices.level` **1** (see [levels](../../README.md#levels)).

Auto-fixable: no. Removing `#[Depends]` leaves the test without the value it received; building that value is up to the test.

Related skill: none: no section of the [marketplace skills](https://github.com/FabienSalles/claude-marketplace) states this practice yet.

## What it detects

A test method carrying one of PHPUnit's `#[Depends*]` attributes (`Depends`, `DependsUsingDeepClone`, `DependsUsingShallowClone`, `DependsExternal*`, `DependsOnClass*`) or a `@depends` annotation.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noDependsBetweenTests: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoDependsBetweenTests\Failing;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

final class Stack
{
    /** @var list<string> */
    private array $items = [];

    public function push(string $item): void
    {
        $this->items[] = $item;
    }

    /** @return list<string> */
    public function items(): array
    {
        return $this->items;
    }
}

final class StackTest extends TestCase
{
    private static Stack $stack;

    public function testStartsEmpty(): void
    {
        self::$stack = new Stack();

        self::assertSame([], self::$stack->items());
    }

    #[Depends('testStartsEmpty')]
    public function testPushesAnItem(): void
    {
        $stack = self::$stack;

        $stack->push('a');

        self::assertSame(['a'], $stack->items());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noDependsBetweenTests/Failing.php:38:Test testPushesAnItem() depends on another test: it cannot run alone, and it is skipped when the other one fails.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoDependsBetweenTests\Passing;

use PHPUnit\Framework\TestCase;

final class Stack
{
    /** @var list<string> */
    private array $items = [];

    public function push(string $item): void
    {
        $this->items[] = $item;
    }

    /** @return list<string> */
    public function items(): array
    {
        return $this->items;
    }
}

final class StackTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $stack = new Stack();

        self::assertSame([], $stack->items());
    }

    public function testPushesAnItem(): void
    {
        $stack = new Stack();

        $stack->push('a');

        self::assertSame(['a'], $stack->items());
    }
}
```

## Why

Tests must be independent: each one builds its world, acts, asserts, and can run alone, in any order.

- **Isolation.** A dependent test cannot be run on its own (`--filter testPushesAnItem` skips it), nor in parallel, nor in a random order.
- **Hidden failures.** When the test it depends on fails, PHPUnit skips the dependent one: one bug turns into one failure and several skips, and the skipped behaviours are not checked at all.
- **Shared state.** Passing an object from one test to the next means the second test asserts on what the first one did. A failure in the second can come from either.

Source practice: FIRST, Independent / Isolated; a factory or a helper shared by both tests instead of a dependency.

## Known limits

- None known: the attribute or annotation is the whole signal.

## Ignoring

Use the identifier `testPractices.noDependsBetweenTests`, on the line:

```php
// @phpstan-ignore testPractices.noDependsBetweenTests
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noDependsBetweenTests
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noDependsBetweenTests/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
