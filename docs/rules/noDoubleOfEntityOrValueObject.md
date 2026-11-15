# `testPractices.noDoubleOfEntityOrValueObject`

No double of an entity or a value object. Enabled from `testPractices.level` **2** (see [levels](../../README.md#levels)).

Auto-fixable: no. Building the real object needs its constructor arguments, which only the author knows.

Related skill: [php-test-conventions › Quick Reference (PHP-specific)](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/SKILL.md#quick-reference-php-specific).

## What it detects

A double (`createMock()`, `createStub()`, `prophesize()`, `getMockBuilder()`...) of a class that is:

- a Doctrine entity or embeddable, through the `#[ORM\Entity]` / `#[ORM\Embeddable]` attribute or the `@ORM\Entity` / `@ORM\Embeddable` annotation;
- an enum, or a `readonly` class;
- or matched by one of the `fnmatch()` patterns of `testPractices.noDoubleOfEntityOrValueObject.patterns` (`App\Entity\*` and `*\ValueObject\*` by default).

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noDoubleOfEntityOrValueObject: warning
```

```neon
parameters:
    testPractices:
        noDoubleOfEntityOrValueObject:
            patterns:
                - App\Domain\Model\*
                - '*\ValueObject\*'
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoDoubleOfEntityOrValueObject\Failing;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;

#[ORM\Entity]
class Order
{
    private string $status = 'PENDING';

    public function confirm(): void
    {
        $this->status = 'CONFIRMED';
    }

    public function status(): string
    {
        return $this->status;
    }
}

final readonly class OrderConfirmation
{
    public function confirm(Order $order): string
    {
        $order->confirm();

        return sprintf('Order %s', $order->status());
    }
}

final class OrderConfirmationTest extends TestCase
{
    public function testReportsTheNewStatus(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('status')->willReturn('CONFIRMED');

        $message = (new OrderConfirmation())->confirm($order);

        self::assertSame('Order CONFIRMED', $message);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noDoubleOfEntityOrValueObject/Failing.php:40:Test doubles Docs\NoDoubleOfEntityOrValueObject\Failing\Order, a Doctrine entity or embeddable: a plain object without I/O is built, not doubled.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoDoubleOfEntityOrValueObject\Passing;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;

#[ORM\Entity]
class Order
{
    private string $status = 'PENDING';

    public function confirm(): void
    {
        $this->status = 'CONFIRMED';
    }

    public function status(): string
    {
        return $this->status;
    }
}

final readonly class OrderConfirmation
{
    public function confirm(Order $order): string
    {
        $order->confirm();

        return sprintf('Order %s', $order->status());
    }
}

final class OrderConfirmationTest extends TestCase
{
    public function testReportsTheNewStatus(): void
    {
        $order = new Order();

        $message = (new OrderConfirmation())->confirm($order);

        self::assertSame('Order CONFIRMED', $message);
    }
}
```

## Why

Entities and value objects are plain objects: no I/O, no slow dependency, nothing a double would isolate the test from.

- **The double lies about the domain.** A stubbed `Order` answers `'CONFIRMED'` whatever happened to it. The real one only reaches that state through `confirm()`, and the test should go through the same rule.
- **Refactoring resistance.** The stub encodes which getters the code under test calls. Reading the status another way breaks the test without any behaviour change.
- **Cost.** Building the real object is usually one line; a double is two or three, plus the configuration. When building takes many lines, a factory or an object mother shares it between tests.

Source practice: an entity, a value object or any plain domain object is instantiated, not doubled; a double is for a boundary (a repository, a gateway).

## Known limits

- A heavy entity with many required constructor arguments is still reported; give it a factory.
- Only the configured patterns, Doctrine mapping, enums and readonly classes are recognised; a mutable value object outside the patterns is not.

## Ignoring

Use the identifier `testPractices.noDoubleOfEntityOrValueObject`, on the line:

```php
// @phpstan-ignore testPractices.noDoubleOfEntityOrValueObject
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noDoubleOfEntityOrValueObject
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noDoubleOfEntityOrValueObject/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
