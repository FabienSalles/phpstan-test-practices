<?php

declare(strict_types=1);

namespace Fixtures\TestClassSuffix;

use PHPUnit\Framework\TestCase;

abstract class OrderTestCase extends TestCase
{
}

final class OrderTest extends OrderTestCase
{
    public function testPasses(): void
    {
        self::assertTrue(true);
    }
}

final class OrderChecks extends OrderTestCase
{
    public function testNeverRuns(): void
    {
        self::assertTrue(true);
    }
}

final class OrderFactory
{
}
