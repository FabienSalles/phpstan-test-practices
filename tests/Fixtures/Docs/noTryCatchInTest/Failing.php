<?php

declare(strict_types=1);

namespace Docs\NoTryCatchInTest\Failing;

use PHPUnit\Framework\TestCase;

final class AmountParser
{
    public function parse(string $amount): int
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not an amount.', $amount));
        }

        return (int) $amount;
    }
}

final class AmountParserTest extends TestCase
{
    public function testRejectsAnythingButANumber(): void
    {
        try {
            (new AmountParser())->parse('ten');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('"ten" is not an amount.', $exception->getMessage());
        }
    }
}
