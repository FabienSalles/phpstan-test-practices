<?php

declare(strict_types=1);

namespace Fixtures\Fix\NoProductionConstantAsExpected;

use PHPUnit\Framework\TestCase;

final class Order
{
    public const STATUS = 'CONFIRMED';
    public const LIMIT = 10;
    public const RATE = 0.5;
    public const ACTIVE = true;
    public const NONE = null;
    public const STATUSES = ['CONFIRMED'];
}

final class OrderTest extends TestCase
{
    public function testFixesScalarConstants(): void
    {
        $order = new Order();

        self::assertSame(Order::STATUS, $order::STATUS);
        self::assertSame(Order::LIMIT, $order::LIMIT);
        self::assertSame(Order::RATE, $order::RATE);
        self::assertSame(Order::ACTIVE, $order::ACTIVE);
        self::assertSame(Order::NONE, $order::NONE);
        self::assertSame(Order::STATUSES, $order::STATUSES);
    }
}
