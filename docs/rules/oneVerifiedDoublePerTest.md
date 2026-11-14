# `testPractices.oneVerifiedDoublePerTest`

One verified double per test. Enabled from `testPractices.level` **2** (see [levels](../../README.md#levels)).

Auto-fixable: no. Splitting a test into one test per intention needs names and a choice of which collaborators become stubs.

## What it detects

A test method that verifies more than one double. A verified double is a variable or property carrying a PHPUnit `expects()`, a Prophecy expectation (`shouldBeCalled*()`, `shouldNotBeCalled()`, `should()`) or a Prophecy spy verification (`shouldHaveBeenCalled*()`, `shouldNotHaveBeenCalled()`, `shouldHave()`). Stubs (`willReturn()`, `method()`) do not count.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            oneVerifiedDoublePerTest: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\OneVerifiedDoublePerTest\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

interface Mailer
{
    public function send(string $to, string $subject): void;
}

interface Logger
{
    public function info(string $message): void;
}

final readonly class OrderConfirmation
{
    public function __construct(private Mailer $mailer, private Logger $logger)
    {
    }

    public function confirm(string $customer): void
    {
        $this->mailer->send($customer, 'Order confirmed');
        $this->logger->info('Confirmation sent to ' . $customer);
    }
}

final class OrderConfirmationTest extends TestCase
{
    use ProphecyTrait;

    public function testConfirmsTheOrder(): void
    {
        $mailer = $this->prophesize(Mailer::class);
        $logger = $this->prophesize(Logger::class);
        $confirmation = new OrderConfirmation($mailer->reveal(), $logger->reveal());

        $confirmation->confirm('alice@example.com');

        $mailer->send('alice@example.com', 'Order confirmed')->shouldHaveBeenCalled();
        $logger->info(Argument::any())->shouldHaveBeenCalled();
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/oneVerifiedDoublePerTest/Failing.php:38:Test testConfirmsTheOrder() verifies 2 doubles ($mailer, $logger). One intention per test: verify a single double.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\OneVerifiedDoublePerTest\Passing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface Mailer
{
    public function send(string $to, string $subject): void;
}

interface Logger
{
    public function info(string $message): void;
}

final readonly class OrderConfirmation
{
    public function __construct(private Mailer $mailer, private Logger $logger)
    {
    }

    public function confirm(string $customer): void
    {
        $this->mailer->send($customer, 'Order confirmed');
        $this->logger->info('Confirmation sent to ' . $customer);
    }
}

final class NullLogger implements Logger
{
    public function info(string $message): void
    {
    }
}

final class OrderConfirmationTest extends TestCase
{
    use ProphecyTrait;

    public function testEmailsTheCustomer(): void
    {
        $mailer = $this->prophesize(Mailer::class);
        $confirmation = new OrderConfirmation($mailer->reveal(), new NullLogger());

        $confirmation->confirm('alice@example.com');

        $mailer->send('alice@example.com', 'Order confirmed')->shouldHaveBeenCalled();
    }
}
```

## Why

Each verified double is an expectation about an outgoing interaction. Two of them in one test are two intentions:

- **Diagnosis.** When the test fails, its name says "confirms the order", not whether the email or the log line is missing. Split, `testEmailsTheCustomer` and `testLogsTheConfirmation` each say what broke.
- **Refactoring resistance.** Interaction checks are the most coupled to the implementation ([Mocks Aren't Stubs](https://martinfowler.com/articles/mocksArentStubs.html), Martin Fowler). Every extra verified double in a test is another reason for it to break when the implementation changes without the behaviour changing; one per test keeps that cost bounded and visible.
- **Overspecification.** Verifying every collaborator turns the test into a transcript of the method body. The collaborators that are not the point of the test are better as stubs or null objects.

Source practice: one intention per test; xUnit Test Patterns, [Behavior Verification](http://xunitpatterns.com/Behavior%20Verification.html) used sparingly.

## Known limits

- Two verifications on the same double (two calls on `$mailer`) count as one double.
- Verifications made in a helper method are not seen.

## Ignoring

Use the identifier `testPractices.oneVerifiedDoublePerTest`, on the line:

```php
// @phpstan-ignore testPractices.oneVerifiedDoublePerTest
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.oneVerifiedDoublePerTest
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/oneVerifiedDoublePerTest/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
