# `testPractices.spyOverMock`

Spy over mock. Enabled from `testPractices.level` **3** (see [levels](../../README.md#levels)).

Auto-fixable: partly. For a Prophecy expectation written as its own statement (`$double->method(...)->shouldBeCalled*()` or `->shouldNotBeCalled()`), `vendor/bin/phpstan analyse --fix` moves it after the last statement of the test as the matching spy verification (`shouldHaveBeenCalled*()`, `shouldNotHaveBeenCalled()`). An expectation chained with a stub (`->willReturn(...)->shouldBeCalled()`) and PHPUnit's `expects()` are not fixed: PHPUnit has no native spy.

Related skill: [testing-principles › 6. Spy Over Mock (verify AFTER act)](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#6-spy-over-mock-verify-after-act), [php-test-conventions › PHP-specific: Spy Pattern (Prophecy)](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/SKILL.md#php-specific-spy-pattern-prophecy).

## What it detects

A PHPUnit `expects()` (a mock: the verification is declared before the act). Prophecy spies, verified with `shouldHaveBeenCalled()` after the act, and hand-written doubles read after the act are not reported.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            spyOverMock: error
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\SpyOverMock\Failing;

use PHPUnit\Framework\TestCase;

interface Logger
{
    public function info(string $message): void;
}

final readonly class OrderProcessor
{
    public function __construct(private Logger $logger)
    {
    }

    public function process(int $orderId): void
    {
        $this->logger->info(sprintf('Order %d processed', $orderId));
    }
}

final class OrderProcessorTest extends TestCase
{
    public function testLogsTheProcessedOrder(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('info')->with('Order 42 processed');
        $processor = new OrderProcessor($logger);

        $processor->process(42);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/spyOverMock/Failing.php:31:Test testLogsTheProcessedOrder() calls expects(), which sets a verification before the act (mock). Prefer a spy verified after the act.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\SpyOverMock\Passing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface Logger
{
    public function info(string $message): void;
}

final readonly class OrderProcessor
{
    public function __construct(private Logger $logger)
    {
    }

    public function process(int $orderId): void
    {
        $this->logger->info(sprintf('Order %d processed', $orderId));
    }
}

final class OrderProcessorTest extends TestCase
{
    use ProphecyTrait;

    public function testLogsTheProcessedOrder(): void
    {
        $logger = $this->prophesize(Logger::class);
        $processor = new OrderProcessor($logger->reveal());

        $processor->process(42);

        $logger->info('Order 42 processed')->shouldHaveBeenCalled();
    }
}
```

## Why

A mock states its expectation before the code runs; a [Test Spy](http://xunitpatterns.com/Test%20Spy.html) records the calls and the test checks them afterwards:

- **Arrange-Act-Assert.** With a mock, the assertion lives in the arrange block, and a test can have no assertion after its act at all. With a spy, the verification is in the final block like every other assertion, and the test reads in the order things happen.
- **Failure messages.** A mock fails inside the act, at the unexpected call, often with a stack trace that points into the code under test. A spy fails at the assertion line with the calls it received.
- **Coupling.** A mock verifies by default how the call is made (`with()`, `once()`); a spy verifies only what the test asks for, after the fact. Fewer incidental constraints means fewer tests broken by a refactoring that keeps the behaviour.

Source practice: state over interaction verification, and spies over mocks when an interaction must be checked ([Mocks Aren't Stubs](https://martinfowler.com/articles/mocksArentStubs.html)).

## Known limits

- Reported as a warning by default, since mocks are a legitimate PHPUnit idiom; raise it to `error` with `severity.spyOverMock`.
- PHPUnit has no native spy; the recommended alternatives are Prophecy (`phpspec/prophecy-phpunit`) or a hand-written double.

## Ignoring

Use the identifier `testPractices.spyOverMock`, on the line:

```php
// @phpstan-ignore testPractices.spyOverMock
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.spyOverMock
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/spyOverMock/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
