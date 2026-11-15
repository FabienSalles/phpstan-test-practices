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
