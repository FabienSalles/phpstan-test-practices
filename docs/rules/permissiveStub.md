# `testPractices.permissiveStub`

Permissive stub. Enabled from `testPractices.level` **3** (see [levels](../../README.md#levels)).

Auto-fixable: no. Replacing a constraint by `Argument::any()` is mechanical, but whether the constraint was a hidden verification that deserves a spy is not.

Related skill: [php-test-conventions › test-doubles › Prophecy Best Practices: Permissive Arrange, Strict Assert](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/references/test-doubles.md#prophecy-best-practices-permissive-arrange-strict-assert).

## What it detects

A Prophecy stub (`$double->method(...)->willReturn(...)`, or any other `will*()`) whose arguments constrain the call: a literal, a variable, an object, or an `Argument::` matcher other than `Argument::any()` and `Argument::cetera()`. A PHPUnit stub configured with `->with(...)` and no `->expects(...)` is reported too.

Not reported: a call that the test also verifies (`should*()` on the same double and method), and the dispatch case, where the same method is stubbed several times with different arguments to return different values.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            permissiveStub: error
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\PermissiveStub\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface UserRepository
{
    public function find(int $id): ?string;
}

final readonly class Greeter
{
    public function __construct(private UserRepository $users)
    {
    }

    public function greet(int $id): string
    {
        return sprintf('Hello %s', $this->users->find($id) ?? 'stranger');
    }
}

final class GreeterTest extends TestCase
{
    use ProphecyTrait;

    public function testGreetsAKnownUser(): void
    {
        $users = $this->prophesize(UserRepository::class);
        $users->find(42)->willReturn('Alice');

        $greeting = (new Greeter($users->reveal()))->greet(42);

        self::assertSame('Hello Alice', $greeting);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/permissiveStub/Failing.php:34:The stub of $users->find() constrains its arguments: a stub provides data, it does not check how it is called.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\PermissiveStub\Passing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

interface UserRepository
{
    public function find(int $id): ?string;
}

final readonly class Greeter
{
    public function __construct(private UserRepository $users)
    {
    }

    public function greet(int $id): string
    {
        return sprintf('Hello %s', $this->users->find($id) ?? 'stranger');
    }
}

final class GreeterTest extends TestCase
{
    use ProphecyTrait;

    public function testGreetsAKnownUser(): void
    {
        $users = $this->prophesize(UserRepository::class);
        $users->find(Argument::any())->willReturn('Alice');

        $greeting = (new Greeter($users->reveal()))->greet(42);

        self::assertSame('Hello Alice', $greeting);
    }
}
```

## Why

A stub provides data to the code under test. Constraining its arguments turns it into a hidden verification, in the wrong place:

- **Failure messages.** When the code calls `find(41)` instead of `find(42)`, a constrained Prophecy stub returns `null` (no matching promise), and the test fails later on an unrelated assertion, or not at all. A spy verification fails on the call itself, with the received arguments.
- **Refactoring resistance.** The test now depends on how the dependency is called, not only on what it returns. A refactoring that changes the arguments without changing the behaviour breaks it.
- **Intention.** "Stub permissive, verify strict": the stub says what the world returns, the final assertion says what matters.

Source practice: a stub provides data and accepts any arguments; arguments that matter are checked by a spy after the act ([Mocks Aren't Stubs](https://martinfowler.com/articles/mocksArentStubs.html)).

## Known limits

- Reported as a warning: a literal argument is common in small examples and sometimes harmless.
- The Prophecy detection is syntactic (`$variable->method(...)->will*()`); it does not check that the variable holds an `ObjectProphecy`.

## Ignoring

Use the identifier `testPractices.permissiveStub`, on the line:

```php
// @phpstan-ignore testPractices.permissiveStub
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.permissiveStub
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/permissiveStub/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
