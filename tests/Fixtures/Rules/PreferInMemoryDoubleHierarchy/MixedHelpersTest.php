<?php

declare(strict_types=1);

namespace Fixtures\PreferInMemoryDoubleHierarchy;

final class MixedHelpersTest extends OrderTestCase
{
    use MailerDoubles;

    public function testNotifiesAboutThreeOrders(): void
    {
        $repository = $this->createStub(OrderRepository::class);
        $repository->method('count')->willReturn(3);
        $mailer = $this->mailer();

        $mailer->send((string) $repository->count());

        self::assertSame(3, $repository->count());
    }

    public function testNotifiesThroughAHelper(): void
    {
        $repository = $this->sentOrders();

        self::assertSame(1, $repository->count());
    }

    private function sentOrders(): OrderRepository
    {
        return $this->orderRepository(1);
    }
}
