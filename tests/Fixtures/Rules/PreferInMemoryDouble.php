<?php

declare(strict_types=1);

namespace Fixtures\PreferInMemoryDouble;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface OrderRepository
{
    public function count(): int;
}

interface Clock
{
    public function now(): \DateTimeImmutable;
}

interface Mailer
{
    public function send(string $to): void;
}

final class PreferInMemoryDoubleTest extends TestCase
{
    use ProphecyTrait;

    protected function setUp(): void
    {
        $this->createStub(Mailer::class);
    }

    public function testCountsWithAPhpUnitStub(): void
    {
        $repository = $this->createStub(OrderRepository::class);
        $repository->method('count')->willReturn(1);

        self::assertSame(1, $repository->count());
    }

    #[Test]
    public function countsWithAProphecyStub(): void
    {
        $repository = $this->prophesize(OrderRepository::class);
        $repository->count()->willReturn(2);

        self::assertSame(2, $repository->reveal()->count());
    }

    #[DataProvider('provideCounts')]
    public function testCountsWithAMockBuilder(int $count): void
    {
        $repository = $this->getMockBuilder(OrderRepository::class)->getMock();
        $repository->method('count')->willReturn($count);

        self::assertSame($count, $repository->count());
    }

    public function testReadsTheClockOnce(): void
    {
        $clock = $this->createMock(Clock::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01'));

        self::assertSame('2026', $clock->now()->format('Y'));
    }

    public function testSendsWithAHandWrittenDouble(): void
    {
        $mailer = new class () implements Mailer {
            public ?string $sentTo = null;

            public function send(string $to): void
            {
                $this->sentTo = $to;
            }
        };

        $mailer->send('alice@example.com');

        self::assertSame('alice@example.com', $mailer->sentTo);
    }

    /**
     * @return iterable<string, array{count: int}>
     */
    public static function provideCounts(): iterable
    {
        yield 'three orders' => ['count' => 3];
    }

    private function mailer(): Mailer
    {
        return $this->createStub(Mailer::class);
    }
}
