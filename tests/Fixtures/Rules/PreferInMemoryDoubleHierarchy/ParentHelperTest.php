<?php

declare(strict_types=1);

namespace Fixtures\PreferInMemoryDoubleHierarchy;

final class ParentHelperTest extends OrderTestCase
{
    public function testCountsTwoOrders(): void
    {
        $repository = $this->orderRepository(2);

        self::assertSame(2, $repository->count());
    }
}
