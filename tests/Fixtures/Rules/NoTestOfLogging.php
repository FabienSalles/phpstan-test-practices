<?php

declare(strict_types=1);

namespace Fixtures\NoTestOfLogging;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

final class TestHandler
{
    public function hasInfoThatContains(string $message): bool
    {
        return $message !== '';
    }
}

final readonly class Processor
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(): void
    {
        $this->logger->info('processed');
    }
}

final class NoTestOfLoggingTest extends TestCase
{
    use ProphecyTrait;

    public function testVerifiesTheLogger(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);

        (new Processor($logger->reveal()))->process();

        $logger->info('processed')->shouldHaveBeenCalled();
    }

    public function testReadsTheLogHandler(): void
    {
        $handler = new TestHandler();

        self::assertTrue($handler->hasInfoThatContains('processed'));
    }

    public function testStubsTheLogger(): void
    {
        $logger = $this->createStub(LoggerInterface::class);

        (new Processor($logger))->process();

        self::assertTrue(true);
    }
}
