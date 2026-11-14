<?php

declare(strict_types=1);

namespace Fixtures\Severity\Errors;

use PHPUnit\Framework\TestCase;

final class GuardAssertionTest extends TestCase
{
    public function testGuardBeforeTheFinalAssertion(): void
    {
        $result = [1];

        self::assertCount(1, $result);
        self::assertSame([1], $result);
    }
}
