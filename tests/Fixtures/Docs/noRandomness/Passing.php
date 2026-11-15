<?php

declare(strict_types=1);

namespace Docs\NoRandomness\Passing;

use PHPUnit\Framework\TestCase;

final class OrderLabel
{
    public function for(string $reference): string
    {
        return sprintf('Order %s', strtoupper($reference));
    }
}

final class OrderLabelTest extends TestCase
{
    public function testUppercasesTheReference(): void
    {
        $label = (new OrderLabel())->for('ord-42');

        self::assertSame('Order ORD-42', $label);
    }
}
