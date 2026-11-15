# `testPractices.noReflectionOnSut`

No reflection on the system under test. Enabled from `testPractices.level` **2** (see [levels](../../README.md#levels)).

Auto-fixable: no. The replacement is a test through the public API, which only the author can write.

Related skill: none: no section of the [marketplace skills](https://github.com/FabienSalles/claude-marketplace) states this practice yet.

## What it detects

In a test class, a call that uses reflection or closure binding to reach private state:

- `setAccessible()`, `invoke()`, `invokeArgs()`, `getClosure()` on a `ReflectionMethod`;
- `setAccessible()`, `getValue()`, `setValue()` on a `ReflectionProperty`;
- `Closure::bind()`, and `bindTo()` / `call()` on a `Closure`.

The receiver's type comes from PHPStan's inference, so a `ReflectionMethod` obtained through `(new ReflectionClass(...))->getMethod()` is recognised too. Reading metadata (`ReflectionClass::getShortName()`, attributes) and `newInstanceWithoutConstructor()` are not reported.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noReflectionOnSut: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoReflectionOnSut\Failing;

use PHPUnit\Framework\TestCase;

final class Password
{
    private string $hash;

    public function __construct(string $plain)
    {
        $this->hash = $this->hashed($plain);
    }

    public function matches(string $plain): bool
    {
        return hash_equals($this->hash, $this->hashed($plain));
    }

    private function hashed(string $plain): string
    {
        return hash('sha256', $plain);
    }
}

final class PasswordTest extends TestCase
{
    public function testHashesWithSha256(): void
    {
        $password = new Password('secret');
        $hashed = new \ReflectionMethod(Password::class, 'hashed');

        $hash = $hashed->invoke($password, 'secret');

        self::assertSame(hash('sha256', 'secret'), $hash);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noReflectionOnSut/Failing.php:36:The test calls invoke() to reach private state: it is coupled to how the class is written, not to what it does.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoReflectionOnSut\Passing;

use PHPUnit\Framework\TestCase;

final class Password
{
    private string $hash;

    public function __construct(string $plain)
    {
        $this->hash = $this->hashed($plain);
    }

    public function matches(string $plain): bool
    {
        return hash_equals($this->hash, $this->hashed($plain));
    }

    private function hashed(string $plain): string
    {
        return hash('sha256', $plain);
    }
}

final class PasswordTest extends TestCase
{
    public function testMatchesTheSamePassword(): void
    {
        $password = new Password('secret');

        $matches = $password->matches('secret');

        self::assertTrue($matches);
    }
}
```

## Why

A private method or property is an implementation detail; the class may rename, merge or delete it at any time without changing what it does:

- **Refactoring resistance.** A test that invokes `hashed()` by name breaks when the method is inlined or renamed, although every user of `Password` sees the same behaviour. The test then blocks the refactorings it should protect.
- **It tests the wrong thing.** The contract is "a password matches itself and nothing else", not "the hash is SHA-256". Testing the private step pins a choice the class is free to change.
- **A design signal.** A private method that seems to need its own test often wants to be a public method of another, smaller class, tested on its own.

Source practice: drive the code through its public interface.

## Known limits

- Reflection used deliberately on test infrastructure (not on the system under test) is reported too; ignore the line.
- Reflection hidden in a helper of another class is not seen.

## Ignoring

Use the identifier `testPractices.noReflectionOnSut`, on the line:

```php
// @phpstan-ignore testPractices.noReflectionOnSut
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noReflectionOnSut
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noReflectionOnSut/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
