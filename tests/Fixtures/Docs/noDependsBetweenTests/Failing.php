<?php

declare(strict_types=1);

namespace Docs\NoDependsBetweenTests\Failing;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

final class Stack
{
    /** @var list<string> */
    private array $items = [];

    public function push(string $item): void
    {
        $this->items[] = $item;
    }

    /** @return list<string> */
    public function items(): array
    {
        return $this->items;
    }
}

final class StackTest extends TestCase
{
    private static Stack $stack;

    public function testStartsEmpty(): void
    {
        self::$stack = new Stack();

        self::assertSame([], self::$stack->items());
    }

    #[Depends('testStartsEmpty')]
    public function testPushesAnItem(): void
    {
        $stack = self::$stack;

        $stack->push('a');

        self::assertSame(['a'], $stack->items());
    }
}
