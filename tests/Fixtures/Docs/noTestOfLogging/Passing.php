<?php

declare(strict_types=1);

namespace Docs\NoTestOfLogging\Passing;

use PHPUnit\Framework\TestCase;
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
    public function testProcessesTheOrder(): void
    {
        $order = new Order();

        (new OrderProcessor($this->createStub(LoggerInterface::class)))->process($order);

        self::assertSame('PROCESSED', $order->status);
    }
}
