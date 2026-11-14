# `testPractices.testFileInTestsuite`

Test file in a test suite. Enabled from `testPractices.level` **0** (see [levels](../../README.md#levels)).

Auto-fixable: no. Whether to move the file, add a `<file>` entry or change a suite is a project decision.

## What it detects

A concrete `TestCase` subclass whose file is not collected by any `<testsuite>` of the PHPUnit configuration. A file is collected when it is listed in a `<file>` element, or when it is under a `<directory>` and its name starts with the directory's `prefix` (empty by default) and ends with its `suffix` (`Test.php` by default), and in both cases is not under an `<exclude>` of the same suite.

The configuration is `testPractices.phpunitConfiguration` when set (absolute, or relative to the directory PHPStan runs from), otherwise the first of `phpunit.xml`, `phpunit.dist.xml` and `phpunit.xml.dist` found in that directory. Without a configuration the rule stays silent.

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            testFileInTestsuite: warning
```

```neon
parameters:
    testPractices:
        phpunitConfiguration: config/phpunit.xml.dist
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\TestFileInTestsuite\Failing;

use PHPUnit\Framework\TestCase;

final class Cart
{
    /** @var list<int> */
    private array $prices = [];

    public function add(int $price): void
    {
        $this->prices[] = $price;
    }

    public function total(): int
    {
        return array_sum($this->prices);
    }
}

final class CartTest extends TestCase
{
    public function testSumsThePrices(): void
    {
        $cart = new Cart();
        $cart->add(10);

        $cart->add(5);

        self::assertSame(15, $cart->total());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/testFileInTestsuite/Failing.php:25:Test class Docs\TestFileInTestsuite\Failing\CartTest is in a file that no <testsuite> of phpunit.xml.dist includes: PHPUnit never runs it.
```

## Passing example

Same class, in a file the configuration collects. The fixtures share this `phpunit.xml.dist`, which excludes the failing file:

```xml
<testsuite name="docs">
    <directory suffix=".php">.</directory>
    <exclude>testFileInTestsuite/Failing.php</exclude>
</testsuite>
```

```php
<?php

declare(strict_types=1);

namespace Docs\TestFileInTestsuite\Passing;

use PHPUnit\Framework\TestCase;

final class Cart
{
    /** @var list<int> */
    private array $prices = [];

    public function add(int $price): void
    {
        $this->prices[] = $price;
    }

    public function total(): int
    {
        return array_sum($this->prices);
    }
}

final class CartTest extends TestCase
{
    public function testSumsThePrices(): void
    {
        $cart = new Cart();
        $cart->add(10);

        $cart->add(5);

        self::assertSame(15, $cart->total());
    }
}
```

## Why

A test that PHPUnit does not collect is worse than no test: it is reviewed, maintained and trusted, and it never runs.

- **Regression detection.** The test was written to fail when a behaviour breaks. Outside every suite, it cannot fail, so the behaviour it describes is unprotected while the code base looks covered.
- **It happens silently.** PHPUnit reports what it ran, not what it skipped: a file moved to a new directory, a suite narrowed to `tests/Unit`, an `<exclude>` added for a slow test, and nothing turns red. Only reading the configuration against the file tree shows it, which is what this rule does on every analysis.
- **Coverage and mutation tools inherit the gap.** Infection and code coverage run the configured suites; a forgotten test does not count there either, so the numbers say less than the code shows.

Source practice: every test file belongs to a test suite, checked in CI (`failOnEmptyTestSuite` covers an empty suite, not a forgotten file).

## Known limits

- Glob patterns in `<directory>` are matched with `fnmatch()`, where `*` also crosses directory separators.
- The `phpVersion`, `groups` and `--testsuite` filters are ignored: a file collected by any suite counts as collected.
- A test class run only through a custom loader or a PHPT file is not seen as collected.

## Ignoring

Use the identifier `testPractices.testFileInTestsuite`, on the line:

```php
// @phpstan-ignore testPractices.testFileInTestsuite
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.testFileInTestsuite
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/testFileInTestsuite/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
