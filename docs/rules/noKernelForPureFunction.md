# `testPractices.noKernelForPureFunction`

No kernel for a pure function. Enabled from `testPractices.level` **3** (see [levels](../../README.md#levels)).

Auto-fixable: no. Changing the parent class may require removing kernel-specific code from the test.

## What it detects

A concrete test class extending Symfony's `KernelTestCase` or `WebTestCase` directly, whose own body never calls `bootKernel()`, `getContainer()`, `createClient()`, `createKernel()` or `getClient()`, nor reads a `client` property. Classes extending a project base class are skipped, since the base class may boot the kernel for them.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noKernelForPureFunction: error
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoKernelForPureFunction\Failing;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class Slugger
{
    public function slug(string $title): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    }
}

final class SluggerTest extends KernelTestCase
{
    public function testSlugsATitle(): void
    {
        $slug = (new Slugger())->slug('Hello World');

        self::assertSame('hello-world', $slug);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/noKernelForPureFunction/Failing.php:17:Test class Docs\NoKernelForPureFunction\Failing\SluggerTest extends Symfony\Bundle\FrameworkBundle\Test\KernelTestCase but never boots the kernel: a test of plain objects is a TestCase.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoKernelForPureFunction\Passing;

use PHPUnit\Framework\TestCase;

final class Slugger
{
    public function slug(string $title): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    }
}

final class SluggerTest extends TestCase
{
    public function testSlugsATitle(): void
    {
        $slug = (new Slugger())->slug('Hello World');

        self::assertSame('hello-world', $slug);
    }
}
```

## Why

Booting a kernel to observe a pure function is the most common overspend in Symfony test suites:

- **Speed.** A `TestCase` runs in microseconds; a kernel test pays for the container, the configuration and often a database. Multiplied by hundreds of tests, it is minutes per run.
- **Coupling.** A kernel test depends on the whole application configuration: an unrelated service definition error turns it red.
- **Clarity.** The parent class tells the reader what kind of test this is. `KernelTestCase` promises wiring; when there is none, the reader looks for it in vain.

Source practice: a transformation of plain objects is a `TestCase`; reach for the kernel only when the container is part of what is tested.

## Known limits

- Reported as a warning.
- Kernel access through a trait or a helper declared elsewhere is not seen, hence the skip of project base classes.

## Ignoring

Use the identifier `testPractices.noKernelForPureFunction`, on the line:

```php
// @phpstan-ignore testPractices.noKernelForPureFunction
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noKernelForPureFunction
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noKernelForPureFunction/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
