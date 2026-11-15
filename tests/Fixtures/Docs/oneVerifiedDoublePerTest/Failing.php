<?php

declare(strict_types=1);

namespace Docs\OneVerifiedDoublePerTest\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
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

final class OrderConfirmationTest extends TestCase
{
    use ProphecyTrait;

    public function testConfirmsTheOrder(): void
    {
        $mailer = $this->prophesize(Mailer::class);
        $logger = $this->prophesize(Logger::class);
        $confirmation = new OrderConfirmation($mailer->reveal(), $logger->reveal());

        $confirmation->confirm('alice@example.com');

        $mailer->send('alice@example.com', 'Order confirmed')->shouldHaveBeenCalled();
        $logger->info(Argument::any())->shouldHaveBeenCalled();
    }
}
