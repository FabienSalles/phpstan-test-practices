<?php

declare(strict_types=1);

namespace Fixtures\ProphecyOverCreateMockCallback;

use PHPUnit\Framework\TestCase;

interface Repository
{
    public function get(string $id): string;
}

final class FinalWithoutInterface
{
    public function get(string $id): string
    {
        return $id;
    }
}

final class ProphecyOverCreateMockCallbackTest extends TestCase
{
    public function testScriptsAMockOfAnInterface(): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->method('get')->willReturnCallback(static fn (string $id): string => strtoupper($id));

        self::assertSame('A', $repository->get('a'));
    }

    public function testScriptsAFinalClassWithoutInterface(): void
    {
        $final = $this->createMock(FinalWithoutInterface::class);
        $final->method('get')->willReturnCallback(static fn (string $id): string => strtoupper($id));

        self::assertSame('A', $final->get('a'));
    }

    public function testStubsWithAValue(): void
    {
        $repository = $this->createStub(Repository::class);
        $repository->method('get')->willReturn('A');

        self::assertSame('A', $repository->get('a'));
    }
}
