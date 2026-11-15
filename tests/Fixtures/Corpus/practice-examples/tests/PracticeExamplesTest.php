<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Order;
use App\Summary\OrderSummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

final class PracticeExamplesTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function badAssertionInTheMiddleOfTheAct(): void
    {
        $order = new Order(1, 'alice', 100.0, 'CONFIRMED');
        self::assertNotNull($order);
        $summary = new OrderSummary($order);

        self::assertTrue($summary->isConfirmed);
    }

    #[Test]
    public function badGuardAssertions(): void
    {
        $result = [new OrderSummary(new Order(1, 'alice', 100.0, 'CONFIRMED'))];

        self::assertCount(1, $result);
        self::assertInstanceOf(OrderSummary::class, $result[0]);
        self::assertNotNull($result[0]->orderId);
        self::assertSame(1, $result[0]->orderId);
    }

    #[Test]
    public function badProphecyMocksBeforeAct(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->info(Argument::cetera())->shouldBeCalled();
        $audit = $this->prophesize(LoggerInterface::class);
        $audit->info(Argument::cetera())->shouldBeCalled();

        $logger->reveal()->info('x');
        $audit->reveal()->info('y');
    }

    #[Test]
    public function goodPermissiveAccessAndSingleAssertion(): void
    {
        $result = [new OrderSummary(new Order(1, 'alice', 100.0, 'CONFIRMED'))];

        $summary = $result[0] ?? null;

        self::assertSame(['orderId' => 1, 'isConfirmed' => true], ['orderId' => $summary?->orderId, 'isConfirmed' => $summary?->isConfirmed]);
    }

    #[Test]
    public function goodSpyVerifiedAfterAct(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->info(Argument::cetera())->willReturn(null);

        $logger->reveal()->info('x');

        $logger->info('x')->shouldHaveBeenCalled();
    }

    #[Test]
    public function goodArgumentThatWithAssertionInsideSpyCheck(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);

        $logger->reveal()->info('x', ['id' => 42]);

        $logger->info(Argument::any(), Argument::that(function (array $context): bool {
            self::assertSame(['id' => 42], $context);

            return true;
        }))->shouldHaveBeenCalled();
    }
}
