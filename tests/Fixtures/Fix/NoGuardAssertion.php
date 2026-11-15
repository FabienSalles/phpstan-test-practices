<?php

declare(strict_types=1);

namespace Fixtures\Fix\NoGuardAssertion;

use PHPUnit\Framework\TestCase;

final class GuardTest extends TestCase
{
    public function testDropsTheGuards(): void
    {
        $lines = ['BOOK-1'];

        self::assertCount(1, $lines);
        self::assertIsString($lines[0]);
        self::assertSame(['BOOK-1'], $lines);
    }
}
