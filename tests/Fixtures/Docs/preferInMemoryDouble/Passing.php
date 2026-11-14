<?php

declare(strict_types=1);

namespace Docs\PreferInMemoryDouble\Passing;

use PHPUnit\Framework\TestCase;

interface OrderRepository
{
    public function countPending(): int;
}

final readonly class InMemoryOrderRepository implements OrderRepository
{
    public function __construct(private int $pending)
    {
    }

    public function countPending(): int
    {
        return $this->pending;
    }
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
        $dashboard = new Dashboard(new InMemoryOrderRepository(0));

        $badge = $dashboard->pendingBadge();

        self::assertSame('', $badge);
    }

    public function testShowsThePendingCount(): void
    {
        $dashboard = new Dashboard(new InMemoryOrderRepository(3));

        $badge = $dashboard->pendingBadge();

        self::assertSame('3', $badge);
    }
}
