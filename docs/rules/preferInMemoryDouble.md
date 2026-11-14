# `testPractices.preferInMemoryDouble`

Prefer an in-memory double. Enabled from `testPractices.level` **3** (see [levels](../../README.md#levels)).

Auto-fixable: no. Writing the in-memory implementation is a design task.

## What it detects

A dependency doubled with `createMock`, `createStub`, `createConfiguredMock`, `createConfiguredStub`, `createPartialMock`, `getMockBuilder`, `getMockForAbstractClass` or `prophesize` on `X::class` in at least `threshold` test methods (2 by default) of the same concrete test class.

The count spans the hierarchy, through PHPStan collectors:

- The test methods of a concrete class are its own, those of the traits it uses and those it inherits from its parent classes, unless it overrides them. Abstract classes are not reported themselves; their test methods count in each concrete subclass.
- A test method doubles `X` when its body creates the double, or when it calls (`$this->`, `self::`, `static::`) a helper that creates it, directly or through other helpers, declared in the class, a parent class or a trait.
- A helper used by N test methods counts N times. An inherited test method counts once in every concrete subclass.
- Doubles created in `setUp()` are not counted.

Reported once per dependency and concrete test class, on the first creation in that class's file, or on the class declaration when every creation lives in a parent class or a trait.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            preferInMemoryDouble: error
```

The threshold is `preferInMemoryDouble.threshold` (2 by default, at least 1).

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\PreferInMemoryDouble\Failing;

use PHPUnit\Framework\TestCase;

interface OrderRepository
{
    public function countPending(): int;
}

final readonly class Dashboard
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function pendingBadge(): string
    {
        $pending = $this->orders->countPending();

        return $pending === 0 ? '' : (string) $pending;
    }
}

final class DashboardTest extends TestCase
{
    public function testHidesTheBadgeWithoutPendingOrders(): void
    {
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('countPending')->willReturn(0);

        $badge = (new Dashboard($orders))->pendingBadge();

        self::assertSame('', $badge);
    }

    public function testShowsThePendingCount(): void
    {
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('countPending')->willReturn(3);

        $badge = (new Dashboard($orders))->pendingBadge();

        self::assertSame('3', $badge);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/preferInMemoryDouble/Failing.php:32:Docs\PreferInMemoryDouble\Failing\OrderRepository is doubled in 2 test methods (testHidesTheBadgeWithoutPendingOrders(), testShowsThePendingCount()). Prefer a hand-written in-memory stub or fake, reused across the tests.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\PreferInMemoryDouble\Passing;

use PHPUnit\Framework\TestCase;

interface OrderRepository
{
    public function countPending(): int;
}

final readonly class InMemoryOrderRepository implements OrderRepository
{
    public function __construct(private int $pending)
    {
    }

    public function countPending(): int
    {
        return $this->pending;
    }
}

final readonly class Dashboard
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function pendingBadge(): string
    {
        $pending = $this->orders->countPending();

        return $pending === 0 ? '' : (string) $pending;
    }
}

final class DashboardTest extends TestCase
{
    public function testHidesTheBadgeWithoutPendingOrders(): void
    {
        $dashboard = new Dashboard(new InMemoryOrderRepository(0));

        $badge = $dashboard->pendingBadge();

        self::assertSame('', $badge);
    }

    public function testShowsThePendingCount(): void
    {
        $dashboard = new Dashboard(new InMemoryOrderRepository(3));

        $badge = $dashboard->pendingBadge();

        self::assertSame('3', $badge);
    }
}
```

## Why

When the same interface is stubbed again and again, each test re-describes how the dependency is called:

- **Refactoring resistance.** A generated stub encodes method names and call shapes (`method('countPending')->willReturn(3)`). Renaming the method or reading the value through another method breaks every test, even though no behaviour changed. A hand-written `InMemoryOrderRepository` is updated once.
- **Readability.** `new InMemoryOrderRepository(3)` says what the world looks like; two lines of stub configuration say how the code happens to query it.
- **Realism.** A fake that implements the interface (and is type-checked by PHPStan against it) behaves consistently across tests; a set of independent stubs can contradict each other or the real implementation. Google's [Software Engineering at Google, chapter 13](https://abseil.io/resources/swe-book/html/ch13.html) recommends real implementations first, then fakes, and mocks last.

Source practice: prefer fakes over mocks ([Fake Object](http://xunitpatterns.com/Fake%20Object.html), xUnit Test Patterns).

## Known limits

- Parent classes and traits are read from the analysed paths only: a base class in `vendor/` contributes nothing.
- A helper called through `parent::`, a variable method name or a trait alias is not followed.
- A helper with a fallback (`$repository ?? $this->createStub(...)`) counts for every caller, even those that pass their own double.
- With `diffBase` set, the finding is kept as soon as its file changed, whatever the line.

## Ignoring

Use the identifier `testPractices.preferInMemoryDouble`, on the line:

```php
// @phpstan-ignore testPractices.preferInMemoryDouble
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.preferInMemoryDouble
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/preferInMemoryDouble/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
