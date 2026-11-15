<?php

declare(strict_types=1);

namespace Docs\NoProductionConstantAsExpected\Passing;

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

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}

final class OrderTest extends TestCase
{
    public function testConfirmsTheOrder(): void
    {
        $order = new Order();

        $order->confirm();

        self::assertSame('CONFIRMED', $order->status());
    }

    public function testRecognisesAConfirmedStatus(): void
    {
        $order = new Order();
        $order->confirm();

        $confirmed = $order->isConfirmed();

        self::assertTrue($confirmed);
    }
}
