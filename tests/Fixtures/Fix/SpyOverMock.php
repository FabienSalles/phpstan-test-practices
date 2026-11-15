<?php

declare(strict_types=1);

namespace Fixtures\Fix\SpyOverMock;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface Logger
{
    public function info(string $message): ?bool;
}

final class SpyOverMockTest extends TestCase
{
    use ProphecyTrait;

    public function testMovesTheExpectationAfterTheAct(): void
    {
        $logger = $this->prophesize(Logger::class);
        $logger->info('processed')->shouldBeCalledTimes(1);

        $logger->reveal()->info('processed');
    }

    public function testKeepsAStubbedExpectation(): void
    {
        $logger = $this->prophesize(Logger::class);
        $logger->info('processed')->willReturn(true)->shouldBeCalled();

        $logger->reveal()->info('processed');
    }

    public function testKeepsAPhpUnitMock(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('info');

        $logger->info('processed');
    }
}
