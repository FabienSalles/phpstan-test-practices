<?php

declare(strict_types=1);

namespace Docs\OneVerifiedDoublePerTest\Passing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface Mailer
{
    public function send(string $to, string $subject): void;
}

interface Logger
{
    public function info(string $message): void;
}

final readonly class OrderConfirmation
{
    public function __construct(private Mailer $mailer, private Logger $logger)
    {
    }

    public function confirm(string $customer): void
    {
        $this->mailer->send($customer, 'Order confirmed');
        $this->logger->info('Confirmation sent to ' . $customer);
    }
}

final class NullLogger implements Logger
{
    public function info(string $message): void
    {
    }
}

final class OrderConfirmationTest extends TestCase
{
    use ProphecyTrait;

    public function testEmailsTheCustomer(): void
    {
        $mailer = $this->prophesize(Mailer::class);
        $confirmation = new OrderConfirmation($mailer->reveal(), new NullLogger());

        $confirmation->confirm('alice@example.com');

        $mailer->send('alice@example.com', 'Order confirmed')->shouldHaveBeenCalled();
    }
}
