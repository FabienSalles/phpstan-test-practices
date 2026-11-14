<?php

declare(strict_types=1);

namespace Docs\SpyOverMock\Failing;

use PHPUnit\Framework\TestCase;

interface Logger
{
    public function info(string $message): void;
}

final readonly class OrderProcessor
{
    public function __construct(private Logger $logger)
    {
    }

    public function process(int $orderId): void
    {
        $this->logger->info(sprintf('Order %d processed', $orderId));
    }
}

final class OrderProcessorTest extends TestCase
{
    public function testLogsTheProcessedOrder(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('info')->with('Order 42 processed');
        $processor = new OrderProcessor($logger);

        $processor->process(42);
    }
}
