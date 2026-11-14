<?php

declare(strict_types=1);

namespace Fixtures\PreferInMemoryDoubleHierarchy;

use PHPUnit\Framework\TestCase;

abstract class OrderTestCase extends TestCase
{
    public function testCountsNothingByDefault(): void
    {
        $repository = $this->orderRepository(0);

        self::assertSame(0, $repository->count());
    }

    protected function orderRepository(int $count): OrderRepository
    {
        $repository = $this->createStub(OrderRepository::class);
        $repository->method('count')->willReturn($count);

        return $repository;
    }
}
