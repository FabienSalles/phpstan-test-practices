<?php

declare(strict_types=1);

namespace Docs\NoRandomness\Failing;

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
        $reference = uniqid('ord-');

        $label = (new OrderLabel())->for($reference);

        self::assertStringStartsWith('Order ORD-', $label);
    }
}
