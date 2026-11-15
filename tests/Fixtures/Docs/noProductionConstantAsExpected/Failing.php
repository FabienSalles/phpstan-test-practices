<?php

declare(strict_types=1);

namespace Docs\NoProductionConstantAsExpected\Failing;

use PHPUnit\Framework\TestCase;

final class Order
{
    public const STATUS_CONFIRMED = 'CONFIRMED';

    private string $status = 'PENDING';

    public function confirm(): void
    {
        $this->status = self::STATUS_CONFIRMED;
    }

    public function status(): string
    {
        return $this->status;
    }
}

final class OrderTest extends TestCase
{
    public function testConfirmsTheOrder(): void
    {
        $order = new Order();

        $order->confirm();

        self::assertSame(Order::STATUS_CONFIRMED, $order->status());
    }
}
