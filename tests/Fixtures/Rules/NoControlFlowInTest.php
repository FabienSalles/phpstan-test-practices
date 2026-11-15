<?php

declare(strict_types=1);

namespace Fixtures\NoControlFlowInTest;

use PHPUnit\Framework\TestCase;

final class NoControlFlowInTestTest extends TestCase
{
    public function testWithControlFlow(): void
    {
        $prices = [10, 5];
        $total = 0;

        foreach ($prices as $price) {
            $total += $price;
        }

        if ($total > 0) {
            self::assertSame(15, $total);
        }

        $label = match (true) {
            $total > 10 => $this->label('high'),
            default => 'low',
        };

        self::assertSame('high', $label);
    }

    public function testWithoutControlFlow(): void
    {
        $total = array_sum(array_map(static fn (int $price): int => $price > 0 ? $price : 0, [10, 5]));
        $label = match (true) {
            $total > 10 => 'high',
            default => 'low',
        };

        self::assertSame(['total' => 15, 'label' => 'high'], ['total' => $total, 'label' => $label]);
    }

    private function label(string $label): string
    {
        foreach ([$label] as $item) {
            return $item;
        }

        return '';
    }
}
