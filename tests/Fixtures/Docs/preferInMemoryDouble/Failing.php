<?php

declare(strict_types=1);

namespace Docs\PreferInMemoryDouble\Failing;

use PHPUnit\Framework\TestCase;

interface OrderRepository
{
    public function countPending(): int;
}

final readonly class Dashboard
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function pendingBadge(): string
    {
        $pending = $this->orders->countPending();

        return $pending === 0 ? '' : (string) $pending;
    }
}

final class DashboardTest extends TestCase
{
    public function testHidesTheBadgeWithoutPendingOrders(): void
    {
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('countPending')->willReturn(0);

        $badge = (new Dashboard($orders))->pendingBadge();

        self::assertSame('', $badge);
    }

    public function testShowsThePendingCount(): void
    {
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('countPending')->willReturn(3);

        $badge = (new Dashboard($orders))->pendingBadge();

        self::assertSame('3', $badge);
    }
}
