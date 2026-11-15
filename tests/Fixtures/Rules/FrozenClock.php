<?php

declare(strict_types=1);

namespace Fixtures\FrozenClock;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrozenClockTest extends TestCase
{
    public function testReadsTheSystemClock(): void
    {
        $now = new \DateTimeImmutable();
        $tomorrow = new \DateTime('tomorrow');
        $timestamp = time();
        $today = date('Y-m-d');

        self::assertSame([$now, $tomorrow, $timestamp, $today], [$now, $tomorrow, $timestamp, $today]);
    }

    #[DataProvider('provideDates')]
    public function testUsesAbsoluteDates(\DateTimeImmutable $date): void
    {
        $formatted = date('Y-m-d', $date->getTimestamp());

        self::assertSame('2026-11-12', $formatted);
    }

    /**
     * @return iterable<string, array{\DateTimeImmutable}>
     */
    public static function provideDates(): iterable
    {
        yield 'absolute' => [new \DateTimeImmutable('2026-11-12 09:00')];
        yield 'relative' => [new \DateTimeImmutable('+1 day')];
    }
}
