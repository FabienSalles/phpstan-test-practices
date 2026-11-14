# PHPStan Test Practices

PHPStan rules that check the **shape** of PHPUnit tests: assertions last, no guard assertions, literal expected values, one verified double per test, spies over mocks.

A test earns trust in two dynamic ways: it fails when the implementation is removed, and it kills mutants (Infection). Both run the suite. This extension adds a cheaper, static level in front of them: it reads the test code and rejects the shapes that make a test lie or hard to read, in seconds, before anything runs. It does not replace the dynamic checks: a well-shaped test can still assert nothing useful.

## Installation

```bash
composer require --dev fabiensalles/phpstan-test-practices
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer) the extension is registered automatically. Without it, include it in your `phpstan.neon`:

```neon
includes:
    - vendor/fabiensalles/phpstan-test-practices/extension.neon
```

Then analyse your tests (the rules only look at classes extending `PHPUnit\Framework\TestCase`, so analysing `src/` alongside is harmless):

```neon
parameters:
    paths:
        - tests
```

The rules run at any PHPStan level. Your test base classes (Symfony `KernelTestCase`, etc.) must be discoverable by PHPStan, otherwise the test class is not recognised as a `TestCase` and is silently skipped.

## Errors and warnings

PHPStan 2 has no severity: every reported error fails the analysis. This extension adds two levels:

- **error**: reported, exit code 1.
- **warning**: reported, exit code 0 when no error remains.

The extension sets PHPStan's `errorFormat` parameter to `testPractices`, an [error formatter](https://phpstan.org/developing-extensions/error-formatters) that prints the findings with an inner formatter (`table` by default), then returns 0 when every remaining finding is a warning. Each rule attaches its severity to the error as metadata. Any other PHPStan error, an internal error, or a configuration error keeps the inner formatter's exit code.

```text
 ------ ---------------------------------------------------------------------
  Line   WarningOnlyTest.php
 ------ ---------------------------------------------------------------------
  19     Test testLogsWithAMock() calls expects(), which sets a verification
         before the act (mock). Prefer a spy verified after the act.
         🪪  testPractices.spyOverMock
 ------ ---------------------------------------------------------------------

 [ERROR] Found 1 error

Test practices: 0 blocking error(s), 1 non-blocking warning(s).
```

Passing `--error-format=<name>` on the command line bypasses the severity formatter, and every finding then fails the build. To keep severities with another output (for instance GitHub annotations in CI), set the inner formatter instead:

```neon
parameters:
    testPractices:
        errorFormat: github
```

If your configuration already sets `errorFormat`, set it back to `testPractices` and move your value to `testPractices.errorFormat`.

## Configuration

All parameters with their defaults:

```neon
parameters:
    testPractices:
        errorFormat: table
        severity:
            assertionsLast: error
            noGuardAssertion: error
            noComputedExpectedValue: error
            noProductionConstantAsExpected: error
            oneVerifiedDoublePerTest: error
            spyOverMock: warning
            preferInMemoryDouble: warning
        preferInMemoryDouble:
            threshold: 2
        assertionCount:
            warningFrom: 3
            errorFrom: 4
```

| Parameter | Values | Meaning |
|---|---|---|
| `errorFormat` | any PHPStan formatter name | Formatter that prints the findings |
| `severity.<rule>` | `error`, `warning` | Severity of each rule |
| `preferInMemoryDouble.threshold` | int ≥ 1 | Number of test methods doubling the same class before it is reported |
| `assertionCount.warningFrom` | int ≥ 1 | Assertions per test from which a warning is reported |
| `assertionCount.errorFrom` | int ≥ 1 | Assertions per test from which an error is reported |

## Rules

A test method is a public, non-static method of a `TestCase` subclass named `test*`, or carrying `#[Test]` or `@test`. Data providers (static) are never treated as tests. Assertions are `assert*` calls through `self::`, `static::`, `SomeClass::` or `$this->`, and Prophecy spy verifications (`shouldHaveBeenCalled()`, `shouldHaveBeenCalledTimes()`, `shouldHaveBeenCalledOnce()`, `shouldNotHaveBeenCalled()`, `shouldHave()`).

| Identifier | Default | Rationale |
|---|---|---|
| `testPractices.assertionsLast` | error | Arrange, act, assert: an assertion before the act, or hidden in an arrange callback, reads as a step of the scenario and fails with a confusing message |
| `testPractices.noGuardAssertion` | error | A guard (`assertCount`, `assertInstanceOf`, `assertNotNull`, `assertIs*`...) stops the test before the real assertion shows its diff |
| `testPractices.noComputedExpectedValue` | error | An expected value computed with the same logic as the code passes by construction |
| `testPractices.noProductionConstantAsExpected` | error | Comparing a production constant with itself never fails when its value changes |
| `testPractices.oneVerifiedDoublePerTest` | error | Several verified doubles mean several intentions in one test |
| `testPractices.spyOverMock` | warning | A verification set before the act (mock) breaks arrange-act-assert; a spy verifies after the act |
| `testPractices.preferInMemoryDouble` | warning | The same dependency doubled in several tests is a sign that a reusable in-memory implementation would be shorter and sturdier |
| `testPractices.assertionCount` | 3: warning, 4+: error | One structured assertion reads better and fails with a complete diff; many assertions usually hide several intentions |

### `testPractices.assertionsLast`

Every top-level statement after the first assertion is reported, and so is any assertion inside a closure, arrow function or anonymous class of the arrange. An assertion inside `Argument::that()` within a spy verification is part of the final block and stays allowed.

```php
// ❌
$mailer->method('send')->willReturnCallback(function (Email $email): void {
    self::assertSame('alice@example.com', $email->getTo()[0]->getAddress());
});
$processor->process($order);

// ✅
$mailer = new SpyMailer();
$processor = new OrderProcessor($mailer);

$processor->process($order);

self::assertSame('alice@example.com', $mailer->lastEmail()?->getTo()[0]?->getAddress());
```

### `testPractices.noGuardAssertion`

```php
// ❌
self::assertCount(1, $result);
self::assertInstanceOf(OrderSummary::class, $result[0]);
self::assertSame(1, $result[0]->orderId);

// ✅
self::assertSame(1, ($result[0] ?? null)?->orderId);
```

### `testPractices.noComputedExpectedValue`

Reports `assertSame`/`assertEquals` whose expected value (or the variable assigned to it) contains a `match`, a ternary or arithmetic (`+ - * / % **`).

```php
// ❌
$expected = match (true) {
    $order->total >= 100 => $order->total * 0.05,
    default => 0.0,
};
self::assertSame($expected, $calculator->discount($order));

// ✅ a literal per case, from a data provider
self::assertSame($expectedDiscount, $calculator->discount($order));
```

### `testPractices.noProductionConstantAsExpected`

Only the **expected** argument of an assertion is checked: the argument of an assertion method whose first parameter is named `$expected…` (`assertSame`, `assertEquals`, `assertNotSame`, `assertNotEquals`, `assertEqualsCanonicalizing`, `assertCount`, `assertJsonStringEqualsJsonString`...), passed positionally or as a named argument. A production constant anywhere else is fine: building a fixture or a stub (arrange), calling the code under test (act), in a data provider, or as the actual value. Enum cases, constants of the test class itself, built-in classes and `Foo::class` are not reported.

```php
// ❌
self::assertSame(OrderController::STATUS_CONFIRMED, $payload['status']);

// ✅
self::assertSame('CONFIRMED', $payload['status']);

// ✅ the constant drives the scenario, the expectation stays literal
$order = new Order(OrderController::STATUS_CONFIRMED);
$payload = $controller->show($order);
self::assertSame('CONFIRMED', $payload['status']);
```

When the constant really is the contract under test, keep it and document the intent on the line:

```php
self::assertSame(Config::DEFAULT_LIMIT, $service->limit()); // @phpstan-ignore testPractices.noProductionConstantAsExpected
```

The default severity of this rule is `error`; whether it should be a warning is still an open decision, hence the `severity.noProductionConstantAsExpected` parameter.

### `testPractices.oneVerifiedDoublePerTest`

A verified double is one carrying `expects()` (PHPUnit), a Prophecy expectation (`shouldBeCalled*()`, `shouldNotBeCalled()`, `should()`) or a Prophecy spy verification. Stubs do not count.

```php
// ❌
$logger->info(Argument::any())->shouldBeCalled();
$processor->process($order);
$mailer->send(Argument::any())->shouldHaveBeenCalled();

// ✅ one test per intention
$processor->process($order);

$mailer->send(Argument::any())->shouldHaveBeenCalled();
```

### `testPractices.spyOverMock`

```php
// ❌
$logger->expects(self::once())->method('info')->with('Order processed');
$processor->process($order);

// ✅
$logger = $this->prophesize(LoggerInterface::class);
$processor = new OrderProcessor($logger->reveal());

$processor->process($order);

$logger->info('Order processed')->shouldHaveBeenCalled();
```

### `testPractices.preferInMemoryDouble`

Counts `createMock`, `createStub`, `createConfiguredMock`, `createConfiguredStub`, `createPartialMock`, `getMockBuilder`, `getMockForAbstractClass` and `prophesize` calls on `X::class`, per test method. Doubles created in `setUp()` or helper methods are not counted. Reported once per dependency, on its first creation.

```php
// ❌ in three test methods of the same class
$repository = $this->createStub(OrderRepository::class);
$repository->method('find')->willReturn($order);

// ✅ one hand-written double, reused
$repository = new InMemoryOrderRepository([$order]);
```

### `testPractices.assertionCount`

Counts the assertions of each test method, anywhere in its body. An assertion nested in another one (`Argument::that()` inside a spy verification) is not counted again. 1 is preferred, 2 is allowed, 3 is a warning, 4 or more an error.

```php
// ❌ 4 assertions: error
self::assertSame(42, $order->id);
self::assertSame('alice', $order->customer);
self::assertSame(250.0, $order->total);
self::assertSame('CONFIRMED', $order->status);

// ✅ 1 structured assertion
self::assertSame(
    ['id' => 42, 'customer' => 'alice', 'total' => 250.0, 'status' => 'CONFIRMED'],
    ['id' => $order->id, 'customer' => $order->customer, 'total' => $order->total, 'status' => $order->status],
);
```

## Ignoring a finding

Use PHPStan's usual mechanisms with the rule identifier:

```php
self::assertSame(Config::DEFAULT_LIMIT, $limit); // @phpstan-ignore testPractices.noProductionConstantAsExpected
```

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.spyOverMock
            path: tests/Legacy/*
```

A baseline (`--generate-baseline`) works as well for an existing suite.

## Compatibility

Tested in this repository's CI matrix and locally with Docker:

| | Lowest tested | Highest tested |
|---|---|---|
| PHP | 8.2 | 8.4 |
| PHPStan | 2.1.0 | 2.3.5 |
| PHPUnit (fixtures and test suite) | 11.5.50 | 11.5.58 |
| Prophecy (`phpspec/prophecy`) | 1.19.0 | 1.26.1 |
| `phpspec/prophecy-phpunit` | 2.3.0 | 2.5.0 |

The rules read the syntax of PHPUnit and Prophecy calls; they do not depend on a PHPUnit or Prophecy version at runtime. PHPUnit 12 is not part of the matrix.

## License

MIT, see [LICENSE](LICENSE).
