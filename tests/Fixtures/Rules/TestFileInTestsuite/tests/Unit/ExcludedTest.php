<?php

declare(strict_types=1);

namespace Fixtures\TestFileInTestsuite;

use PHPUnit\Framework\TestCase;

final class ExcludedTest extends TestCase
{
    public function testRuns(): void
    {
        self::assertTrue(true);
    }
}
