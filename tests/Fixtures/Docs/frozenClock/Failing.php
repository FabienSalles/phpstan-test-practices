<?php

declare(strict_types=1);

namespace Docs\FrozenClock\Failing;

use PHPUnit\Framework\TestCase;

final class Subscription
{
    private ?\DateTimeImmutable $endsAt = null;

    public function start(\DateTimeImmutable $now): void
    {
        $this->endsAt = $now->modify('+30 days');
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return $this->endsAt !== null && $now < $this->endsAt;
    }
}

final class SubscriptionTest extends TestCase
{
    public function testIsActiveRightAfterTheStart(): void
    {
        $subscription = new Subscription();
        $subscription->start(new \DateTimeImmutable());

        $active = $subscription->isActive(new \DateTimeImmutable('+29 days'));

        self::assertTrue($active);
    }
}
