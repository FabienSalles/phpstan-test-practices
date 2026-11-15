<?php

declare(strict_types=1);

namespace Docs\SpyOverMock\Passing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

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
    use ProphecyTrait;

    public function testLogsTheProcessedOrder(): void
    {
        $logger = $this->prophesize(Logger::class);
        $processor = new OrderProcessor($logger->reveal());

        $processor->process(42);

        $logger->info('Order 42 processed')->shouldHaveBeenCalled();
    }
}
