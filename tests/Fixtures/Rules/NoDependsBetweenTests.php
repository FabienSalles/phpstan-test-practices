<?php

declare(strict_types=1);

namespace Fixtures\NoDependsBetweenTests;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

final class NoDependsBetweenTestsTest extends TestCase
{
    public function testCreatesAStack(): array
    {
        $stack = [];

        self::assertSame([], $stack);

        return $stack;
    }

    /**
     * @param list<int> $stack
     */
    #[Depends('testCreatesAStack')]
    public function testPushesWithAnAttribute(array $stack): void
    {
        $stack[] = 1;

        self::assertSame([1], $stack);
    }

    /**
     * @depends testCreatesAStack
     * @param list<int> $stack
     */
    public function testPushesWithAnAnnotation(array $stack): void
    {
        $stack[] = 2;

        self::assertSame([2], $stack);
    }
}
