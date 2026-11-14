<?php

declare(strict_types=1);

namespace Fixtures\NoProductionConstantAsExpected;

use PHPUnit\Framework\TestCase;

final class OrderController
{
    public const STATUS_CONFIRMED = 'CONFIRMED';

    public function confirm(): string
    {
        return self::STATUS_CONFIRMED;
    }
}

enum OrderStatus: string
{
    case Confirmed = 'CONFIRMED';
}

final class NoProductionConstantAsExpectedTest extends TestCase
{
    private const EXPECTED_STATUS = 'CONFIRMED';

    public function testProductionConstantAsExpected(): void
    {
        $status = (new OrderController())->confirm();

        self::assertSame(OrderController::STATUS_CONFIRMED, $status);
    }

    public function testProductionConstantAsExpectedWithAssertEquals(): void
    {
        $status = (new OrderController())->confirm();

        $this->assertEquals(OrderController::STATUS_CONFIRMED, $status);
    }

    public function testLiteralExpected(): void
    {
        $status = (new OrderController())->confirm();

        self::assertSame('CONFIRMED', $status);
    }

    public function testEnumCaseExpected(): void
    {
        $status = OrderStatus::from((new OrderController())->confirm());

        self::assertSame(OrderStatus::Confirmed, $status);
    }

    public function testOwnConstantExpected(): void
    {
        $status = (new OrderController())->confirm();

        self::assertSame(self::EXPECTED_STATUS, $status);
    }

    public function testClassNameExpected(): void
    {
        $controller = new OrderController();

        self::assertSame(OrderController::class, $controller::class);
    }

    public function testBuiltinConstantExpected(): void
    {
        $format = \DateTimeInterface::ATOM;

        self::assertSame(\DateTimeInterface::ATOM, $format);
    }

    public function testConstantDeliberatelyPartOfTheContract(): void
    {
        $status = (new OrderController())->confirm();

        self::assertSame(OrderController::STATUS_CONFIRMED, $status); // @phpstan-ignore testPractices.noProductionConstantAsExpected
    }
}
