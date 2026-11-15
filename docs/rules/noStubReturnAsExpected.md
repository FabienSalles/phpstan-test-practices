# `testPractices.noStubReturnAsExpected`

No stub return value as expected value. Enabled from `testPractices.level` **0** (see [levels](../../README.md#levels)).

Auto-fixable: no. What the test should expect instead depends on what the code under test computes.

Related skill: [testing-principles › 10. Mocks Can Hide Real Bugs](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md#10-mocks-can-hide-real-bugs).

## What it detects

An assertion whose expected argument is a variable, or a `new` expression, that the same test passes to a stub's `willReturn()`, `willReturnMap()` or `willReturnOnConsecutiveCalls()` (PHPUnit or Prophecy).

## Severity

Default severity: **error**. Change it in your `phpstan.neon`:

```neon
parameters:
    testPractices:
        severity:
            noStubReturnAsExpected: warning
```

## Failing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoStubReturnAsExpected\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

final readonly class Product
{
    public function __construct(public string $name, public int $price)
    {
    }
}

interface ProductDecoder
{
    public function decode(string $json): Product;
}

final readonly class Catalog
{
    public function __construct(private ProductDecoder $decoder)
    {
    }

    public function product(string $json): Product
    {
        return $this->decoder->decode($json);
    }
}

final class CatalogTest extends TestCase
{
    use ProphecyTrait;

    public function testReadsAProduct(): void
    {
        $product = new Product('book', 12);
        $decoder = $this->prophesize(ProductDecoder::class);
        $decoder->decode(Argument::any())->willReturn($product);

        $read = (new Catalog($decoder->reveal()))->product('{"name":"book","price":12}');

        self::assertSame($product, $read);
    }
}
```

`vendor/bin/phpstan analyse --error-format=raw` reports (exit code 1):

```text
tests/Fixtures/Docs/noStubReturnAsExpected/Failing.php:47:Test testReadsAProduct() expects $product, the value a stub returns: the assertion checks the stub configuration, not the code under test.
```

## Passing example

```php
<?php

declare(strict_types=1);

namespace Docs\NoStubReturnAsExpected\Passing;

use PHPUnit\Framework\TestCase;

final readonly class Product
{
    public function __construct(public string $name, public int $price)
    {
    }
}

interface ProductDecoder
{
    public function decode(string $json): Product;
}

final readonly class Catalog
{
    public function __construct(private ProductDecoder $decoder)
    {
    }

    public function product(string $json): Product
    {
        return $this->decoder->decode($json);
    }
}

final class JsonProductDecoder implements ProductDecoder
{
    public function decode(string $json): Product
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new Product($data['name'], $data['price']);
    }
}

final class CatalogTest extends TestCase
{
    public function testReadsAProduct(): void
    {
        $catalog = new Catalog(new JsonProductDecoder());

        $read = $catalog->product('{"name":"book","price":12}');

        self::assertEquals(new Product('book', 12), $read);
    }
}
```

## Why

When a stub returns `$product` and the test then expects `$product`, the assertion compares the stub with itself:

- **It passes by construction.** The code under test only has to pass the value through. Delete its logic, return what the collaborator returned, and the test is still green: it checks the stub configuration, not the code.
- **It hides the real contract.** The interesting behaviour (decoding JSON into a `Product`, mapping a DTO) is in the stubbed collaborator, so nothing tests it.
- **What to do instead.** Use the real collaborator (a real decoder, an in-memory repository) and expect the literal result, or assert on what the code computes from the stubbed value.

Source practice: a mock that returns the expected value only tests itself; expected values are literals.

## Known limits

- A variable reassigned between the stub and the assertion is still matched by name.
- An expected value derived from the stubbed one (`$product->name`) is not reported.

## Ignoring

Use the identifier `testPractices.noStubReturnAsExpected`, on the line:

```php
// @phpstan-ignore testPractices.noStubReturnAsExpected
```

or in the configuration:

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.noStubReturnAsExpected
            path: tests/Legacy/*
```

Both examples on this page are fixtures of `tests/Fixtures/Docs/noStubReturnAsExpected/`; `tests/Docs/RuleDocumentationTest.php` checks that this page shows them verbatim and that PHPStan reports exactly the output above.
