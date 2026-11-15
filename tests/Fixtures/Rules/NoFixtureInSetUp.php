<?php

declare(strict_types=1);

namespace Fixtures\NoFixtureInSetUp;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

interface Clock
{
    public function now(): \DateTimeImmutable;
}

final class Order
{
}

final class NoFixtureInSetUpTest extends TestCase
{
    private Clock $clock;
    private Order $order;
    private static string $environment;
    private Order $other;

    protected function setUp(): void
    {
        $this->clock = $this->createStub(Clock::class);
        $this->order = new Order();
        self::$environment = getenv('APP_ENV') ?: 'test';
    }

    #[Before]
    public function prepare(): void
    {
        $this->other = new Order();
    }

    public function testUsesTheFixtures(): void
    {
        $order = $this->order;

        self::assertSame([$this->other, $this->clock, 'test'], [$order === $this->other ? $order : $this->other, $this->clock, self::$environment]);
    }
}
