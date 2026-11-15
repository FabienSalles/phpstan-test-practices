# `testPractices.noTestOfLogging`

No test of logging. Enabled from `testPractices.level` **3** (see [levels](../../README.md#levels)).

Auto-fixable: no. Whether the log is the behaviour under test is an intention only the author knows.

Related skill: [testing-principles › Logging and Monitoring](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#logging-and-monitoring).

## What it detects

Disabled by default; enable it with:

```neon
parameters:
    testPractices:
        noTestOfLogging:
            enabled: true
```

Once enabled: a verification (`expects()`, a Prophecy expectation or spy verification) on a double of `Psr\Log\LoggerInterface` (or of a class implementing it), or a log handler read such as `hasInfoThatContains()`, `hasRecords()` or `getRecords()` in a test method.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noTestOfLogging: error
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoTestOfLogging\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

final class Order
{
    public string $status = 'PENDING';
}

final readonly class OrderProcessor
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(Order $order): void
    {
        $order->status = 'PROCESSED';
        $this->logger->info('Order processed');
    }
}

final class OrderProcessorTest extends TestCase
{
    use ProphecyTrait;

    public function testLogsTheProcessedOrder(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);

        (new OrderProcessor($logger->reveal()))->process(new Order());

        $logger->info('Order processed')->shouldHaveBeenCalled();
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/noTestOfLogging/Failing.php:39:Test testLogsTheProcessedOrder() checks what is logged: logging is rarely the behaviour under test, and the test breaks when a message is reworded.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoTestOfLogging\Passing;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class Order
{
    public string $status = 'PENDING';
}

final readonly class OrderProcessor
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(Order $order): void
    {
        $order->status = 'PROCESSED';
        $this->logger->info('Order processed');
    }
}

final class OrderProcessorTest extends TestCase
{
    public function testProcessesTheOrder(): void
    {
        $order = new Order();

        (new OrderProcessor($this->createStub(LoggerInterface::class)))->process($order);

        self::assertSame('PROCESSED', $order->status);
    }
}
```

## Why

Logs are usually a side channel, not the behaviour:

- **Refactoring resistance.** Rewording "Order processed" or changing its level breaks the test, although no user sees a difference.
- **Focus.** A test that checks the log next to the real outcome has two intentions; often the log check is the one that fails, for no reason that matters.
- **Overspecification.** Writing a test for every log line multiplies tests with little value.

Keep the check only when the log is a business rule, an audited trace or a monitored metric: an alert someone pages on, a compliance record. Then ignore the line and say why.

Source practice: don't test logging or monitoring unless it is the purpose of the code.

## Known limits

- Opt-in, because many projects verify logs deliberately; the owner decides per project.
- Other loggers (a project-specific interface) are not recognised unless they implement `Psr\Log\LoggerInterface`.

## Ignoring

Use the identifier `testPractices.noTestOfLogging`, on the line:

```php
// @phpstan-ignore testPractices.noTestOfLogging
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noTestOfLogging
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noTestOfLogging/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
