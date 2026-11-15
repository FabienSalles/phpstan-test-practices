<?php

declare(strict_types=1);

namespace Fixtures\NoStubReturnAsExpected;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

final readonly class Product
{
    public function __construct(public string $name)
    {
    }
}

interface Serializer
{
    public function deserialize(string $json): Product;
}

final readonly class Catalog
{
    public function __construct(private Serializer $serializer)
    {
    }

    public function fetch(): Product
    {
        return $this->serializer->deserialize('{"name":"book"}');
    }
}

final class NoStubReturnAsExpectedTest extends TestCase
{
    use ProphecyTrait;

    public function testExpectsTheStubbedVariable(): void
    {
        $expected = new Product('book');
        $serializer = $this->prophesize(Serializer::class);
        $serializer->deserialize(Argument::any())->willReturn($expected);

        $product = (new Catalog($serializer->reveal()))->fetch();

        self::assertSame($expected, $product);
    }

    public function testExpectsTheStubbedObject(): void
    {
        $serializer = $this->createStub(Serializer::class);
        $serializer->method('deserialize')->willReturn(new Product('book'));

        $product = (new Catalog($serializer))->fetch();

        self::assertEquals(new Product('book'), $product);
    }

    public function testExpectsSomethingElse(): void
    {
        $serializer = $this->createStub(Serializer::class);
        $serializer->method('deserialize')->willReturn(new Product('book'));

        $product = (new Catalog($serializer))->fetch();

        self::assertSame('book', $product->name);
    }
}
