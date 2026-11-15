<?php

declare(strict_types=1);

namespace Docs\NoTestOfLogging\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

final class Order
{
    public string $status = 'PENDING';
}

final readonly class OrderProcessor
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(Order $order): void
    {
        $order->status = 'PROCESSED';
        $this->logger->info('Order processed');
    }
}

final class OrderProcessorTest extends TestCase
{
    use ProphecyTrait;

    public function testLogsTheProcessedOrder(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);

        (new OrderProcessor($logger->reveal()))->process(new Order());

        $logger->info('Order processed')->shouldHaveBeenCalled();
    }
}
