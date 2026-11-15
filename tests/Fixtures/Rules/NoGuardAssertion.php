<?php

declare(strict_types=1);

namespace Fixtures\NoGuardAssertion;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Summary
{
    public function __construct(
        public int $orderId,
    ) {
    }
}

final class NoGuardAssertionTest extends TestCase
{
    public function testGuardsBeforeTheFinalAssertion(): void
    {
        $result = [new Summary(1)];

        self::assertCount(1, $result);
        self::assertInstanceOf(Summary::class, $result[0]);
        $this->assertNotNull($result[0]->orderId);
        self::assertIsArray($result);
        self::assertSame(1, $result[0]->orderId);
    }

    public function testPermissiveAccessWithASingleAssertion(): void
    {
        $result = [new Summary(1)];

        $summary = $result[0] ?? null;

        self::assertSame(1, $summary?->orderId);
    }

    public function testGuardAloneIsTheFinalAssertion(): void
    {
        $result = [new Summary(1)];

        self::assertCount(1, $result);
    }

    /**
     * @param list<Summary> $result
     */
    #[DataProvider('provideResults')]
    public function testGuardBeforeAFinalAssertionFedByAProvider(array $result, int $expectedId): void
    {
        self::assertNotEmpty($result);
        self::assertSame($expectedId, $result[0]->orderId);
    }

    /**
     * @return iterable<string, array{result: list<Summary>, expectedId: int}>
     */
    public static function provideResults(): iterable
    {
        self::assertTrue(true);

        yield 'one summary' => ['result' => [new Summary(1)], 'expectedId' => 1];
    }
}
