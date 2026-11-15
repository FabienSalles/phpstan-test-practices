<?php

declare(strict_types=1);

namespace Docs\NoDoubleOfEntityOrValueObject\Passing;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;

#[ORM\Entity]
class Order
{
    private string $status = 'PENDING';

    public function confirm(): void
    {
        $this->status = 'CONFIRMED';
    }

    public function status(): string
    {
        return $this->status;
    }
}

final readonly class OrderConfirmation
{
    public function confirm(Order $order): string
    {
        $order->confirm();

        return sprintf('Order %s', $order->status());
    }
}

final class OrderConfirmationTest extends TestCase
{
    public function testReportsTheNewStatus(): void
    {
        $order = new Order();

        $message = (new OrderConfirmation())->confirm($order);

        self::assertSame('Order CONFIRMED', $message);
    }
}
