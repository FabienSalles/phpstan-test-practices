<?php

declare(strict_types=1);

namespace Fixtures\NoTryCatchInTest;

use PHPUnit\Framework\TestCase;

final class Parser
{
    public function parse(string $input): int
    {
        if ($input === '') {
            throw new \InvalidArgumentException('Empty input');
        }

        return (int) $input;
    }
}

final class NoTryCatchInTestTest extends TestCase
{
    public function testCatchesWithoutFail(): void
    {
        try {
            (new Parser())->parse('');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Empty input', $exception->getMessage());
        }
    }

    public function testCatchesAfterFail(): void
    {
        try {
            (new Parser())->parse('');
            self::fail('No exception thrown');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Empty input', $exception->getMessage());
        }
    }

    public function testCleansUpInFinally(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'test');

        try {
            $value = (new Parser())->parse('3');
        } finally {
            unlink((string) $file);
        }

        self::assertSame(3, $value);
    }

    public function testCatchesInsideAClosure(): void
    {
        $parse = static function (string $input): int {
            try {
                return (new Parser())->parse($input);
            } catch (\InvalidArgumentException) {
                return 0;
            }
        };

        self::assertSame(0, $parse(''));
    }

    private function helperMayCatch(): int
    {
        try {
            return (new Parser())->parse('');
        } catch (\InvalidArgumentException) {
            return 0;
        }
    }
}
