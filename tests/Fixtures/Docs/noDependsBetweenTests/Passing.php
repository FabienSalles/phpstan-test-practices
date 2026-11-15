<?php

declare(strict_types=1);

namespace Docs\NoDependsBetweenTests\Passing;

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
    public function testStartsEmpty(): void
    {
        $stack = new Stack();

        self::assertSame([], $stack->items());
    }

    public function testPushesAnItem(): void
    {
        $stack = new Stack();

        $stack->push('a');

        self::assertSame(['a'], $stack->items());
    }
}
