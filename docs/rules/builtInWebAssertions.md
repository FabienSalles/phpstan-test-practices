# `testPractices.builtInWebAssertions`

Built-in web assertions. Enabled from `testPractices.level` **3** (see [levels](../../README.md#levels)).

Auto-fixable: partly. `vendor/bin/phpstan analyse --fix` replaces `assertSame($code, $client->getResponse()->getStatusCode())` by `self::assertResponseStatusCodeSame($code)`. The selector cases are not fixed: their arguments move to another position and the trimming semantics differ.

## What it detects

In a test class that provides Symfony's web assertions (it has `assertResponseStatusCodeSame()`, as `WebTestCase` does):

- `assertSame`/`assertEquals($code, $client->getResponse()->getStatusCode())` → `assertResponseStatusCodeSame($code)`;
- `assertSame`/`assertEquals($text, [trim(]$crawler->filter($selector)->text()[)])` → `assertSelectorTextSame($selector, $text)`;
- `assertStringContainsString($text, $crawler->filter($selector)->text())` → `assertSelectorTextContains($selector, $text)`;
- `assertCount($count, $crawler->filter($selector))` → `assertSelectorCount($count, $selector)`.

A crawler scoped with `eq()`, `first()`, `last()`, `children()` or `siblings()` before the `filter()` is left alone: there is no built-in equivalent.

## Severity

Default severity: **warning**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            builtInWebAssertions: error
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\BuiltInWebAssertions\Failing;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderControllerTest extends WebTestCase
{
    public function testCreatesAnOrder(): void
    {
        $client = static::createClient();

        $client->request('POST', '/orders');

        self::assertSame(201, $client->getResponse()->getStatusCode());
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 0, since a warning alone does not fail the analysis):

```text
tests/Fixtures/Docs/builtInWebAssertions/Failing.php:17:Use self::assertResponseStatusCodeSame() instead of reading the response by hand: it reads better and fails with the response in the message.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\BuiltInWebAssertions\Passing;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderControllerTest extends WebTestCase
{
    public function testCreatesAnOrder(): void
    {
        $client = static::createClient();

        $client->request('POST', '/orders');

        self::assertResponseStatusCodeSame(201);
    }
}
```

## Why

Symfony ships assertions for what web tests check every day:

- **Failure messages.** `assertSame(201, $statusCode)` fails with "500 is not identical to 201". `assertResponseStatusCodeSame(201)` fails with the response itself, including the exception page, which is usually all one needs to fix the bug.
- **Readability.** `assertSelectorTextContains('.alert-success', 'Saved')` states the intention; the hand-rolled version makes the reader parse a crawler chain.
- **Less code.** No `trim()`, no `->text()`, no `count()`.

Source practice: nearly every hand-rolled crawler read has a one-line built-in equivalent that reads better and fails better.

## Known limits

- Reported as a warning.
- Only the four patterns above are recognised; reading headers, forms or e-mails by hand is not reported yet.

## Ignoring

Use the identifier `testPractices.builtInWebAssertions`, on the line:

```php
// @phpstan-ignore testPractices.builtInWebAssertions
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.builtInWebAssertions
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/builtInWebAssertions/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
