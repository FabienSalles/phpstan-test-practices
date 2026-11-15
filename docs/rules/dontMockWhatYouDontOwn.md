# `testPractices.dontMockWhatYouDontOwn`

Don't mock what you don't own. Enabled from `testPractices.level` **2** (see [levels](../../README.md#levels)).

Auto-fixable: no. The fix is a port and an adapter in production code.

## What it detects

A double (`createMock()`, `createStub()`, `prophesize()`...) of a third-party type listed in `testPractices.dontMockWhatYouDontOwn.classes`, or of a class implementing or extending one of them. The default list:

- `Doctrine\ORM\QueryBuilder`, `Doctrine\ORM\EntityManagerInterface`, `Doctrine\ORM\Query`, `Doctrine\DBAL\Connection`
- `GuzzleHttp\ClientInterface`, `Symfony\Contracts\HttpClient\HttpClientInterface`

The serializer is deliberately not in the list: mocking it is fine when serialization is tested elsewhere; when the test is about the serialized data, build a real serializer or fetch the real service from the container (both accepted).

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            dontMockWhatYouDontOwn: warning
```

```neon
parameters:
    testPractices:
        dontMockWhatYouDontOwn:
            classes:
                - Doctrine\ORM\EntityManagerInterface
                - App\Vendor\SomeSdkClient
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\DontMockWhatYouDontOwn\Failing;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final readonly class OrderArchiver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function archive(): void
    {
        $this->entityManager->flush();
    }
}

final class OrderArchiverTest extends TestCase
{
    public function testFlushesTheArchive(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('flush');

        (new OrderArchiver($entityManager))->archive();

        self::assertTrue(true);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/dontMockWhatYouDontOwn/Failing.php:26:Test doubles Doctrine\ORM\EntityManagerInterface, a type the project does not own (Doctrine\ORM\EntityManagerInterface): the test copies how the library is called and breaks when that changes.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\DontMockWhatYouDontOwn\Passing;

use PHPUnit\Framework\TestCase;

interface Archive
{
    public function store(string $order): void;
}

final class InMemoryArchive implements Archive
{
    /** @var list<string> */
    public array $orders = [];

    public function store(string $order): void
    {
        $this->orders[] = $order;
    }
}

final readonly class OrderArchiver
{
    public function __construct(private Archive $archive)
    {
    }

    public function archive(string $order): void
    {
        $this->archive->store($order);
    }
}

final class OrderArchiverTest extends TestCase
{
    public function testStoresTheOrder(): void
    {
        $archive = new InMemoryArchive();

        (new OrderArchiver($archive))->archive('order-1');

        self::assertSame(['order-1'], $archive->orders);
    }
}
```

## Why

A double of a library type reproduces the library's API in the test: `getQuery()` returns a `Query` whose `getResult()` returns the expected rows.

- **Refactoring resistance.** Replacing the QueryBuilder by DQL, or DQL by a DBAL query, does not change what the repository returns, and still breaks every test that scripted the old calls. The test is coupled to the implementation, not the behaviour.
- **False confidence.** The double behaves as the test author believes the library behaves. If the real `QueryBuilder` needs a `setParameter()` the double does not care about, the test passes and production fails.
- **Where the line goes.** Own a port (`OrderRepository`, `ExchangeRates`), double it in the tests of the code that uses it, and test its Doctrine or HTTP adapter with an integration test against the real library.

Source practice: "Don't mock what you don't own" (Steve Freeman and Nat Pryce, *Growing Object-Oriented Software, Guided by Tests*).

## Known limits

- Only the listed types are checked; extend the list with the SDKs of your project.
- A double of a type that merely uses the library (your own adapter) is not reported.

## Ignoring

Use the identifier `testPractices.dontMockWhatYouDontOwn`, on the line:

```php
// @phpstan-ignore testPractices.dontMockWhatYouDontOwn
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.dontMockWhatYouDontOwn
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/dontMockWhatYouDontOwn/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
