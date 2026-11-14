<?php

declare(strict_types=1);

namespace Docs\NoGuardAssertion\Failing;

use PHPUnit\Framework\TestCase;

final readonly class OrderLine
{
    public function __construct(public string $sku, public int $quantity)
    {
    }
}

final class OrderLinesParser
{
    /** @return list<OrderLine> */
    public function parse(string $csv): array
    {
        return array_map(
            static fn (string $row): OrderLine => new OrderLine(...array_map(trim(...), explode(';', $row)) + [1 => 0]),
            array_filter(explode("\n", $csv)),
        );
    }
}

final class OrderLinesParserTest extends TestCase
{
    public function testParsesOneLine(): void
    {
        $lines = (new OrderLinesParser())->parse("BOOK-1;2\n");

        self::assertCount(1, $lines);
        self::assertSame('BOOK-1', $lines[0]->sku ?? null);
    }
}
