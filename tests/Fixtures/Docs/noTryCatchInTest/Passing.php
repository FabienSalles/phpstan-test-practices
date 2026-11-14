<?php

declare(strict_types=1);

namespace Docs\NoTryCatchInTest\Passing;

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
        $parser = new AmountParser();
        $this->expectExceptionObject(new \InvalidArgumentException('"ten" is not an amount.'));

        $parser->parse('ten');
    }
}
