<?php

declare(strict_types=1);

namespace Fixtures\DataProviderNamedKeys;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataProviderNamedKeysTest extends TestCase
{
    #[DataProvider('provideUnnamed')]
    public function testWithUnnamedSets(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    /**
     * @dataProvider provideReturnedArray
     */
    public function testWithAnnotation(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    #[DataProvider('provideNamed')]
    public function testWithNamedSets(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideUnnamed(): iterable
    {
        yield [1];
        yield 'two' => [2];
    }

    /**
     * @return array<array{int}>
     */
    public static function provideReturnedArray(): array
    {
        return [
            'one' => [1],
            [2],
        ];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNamed(): iterable
    {
        yield 'one' => [1];
    }

    /**
     * @return iterable<array{int}>
     */
    public static function notAProvider(): iterable
    {
        yield [1];
    }
}
