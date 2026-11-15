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
