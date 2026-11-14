<?php

declare(strict_types=1);

namespace Fixtures\NoProductionConstantAsExpected;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderController
{
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public function confirm(): string
    {
        return self::STATUS_CONFIRMED;
    }

    public function isFinal(string $status): bool
    {
        return $status === self::STATUS_CONFIRMED;
    }
}

final class Order
{
    public function __construct(public readonly string $status)
    {
    }
}

interface StatusSource
{
    public function status(): string;
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

    public function testProductionConstantAsExpectedWithAssertNotSame(): void
    {
        $status = (new OrderController())->confirm();

        self::assertNotSame(OrderController::STATUS_CANCELLED, $status);
    }

    public function testProductionConstantAsNamedExpectedArgument(): void
    {
        $status = (new OrderController())->confirm();

        self::assertSame(actual: $status, expected: OrderController::STATUS_CONFIRMED);
    }

    public function testConstantInTheArrange(): void
    {
        $order = new Order(OrderController::STATUS_CONFIRMED);

        self::assertSame('CONFIRMED', $order->status);
    }

    public function testConstantInTheAct(): void
    {
        $isFinal = (new OrderController())->isFinal(OrderController::STATUS_CONFIRMED);

        self::assertTrue($isFinal);
    }

    public function testConstantInAStubConfiguration(): void
    {
        $source = $this->createStub(StatusSource::class);
        $source->method('status')->willReturn(OrderController::STATUS_CONFIRMED);

        self::assertSame('CONFIRMED', $source->status());
    }

    #[DataProvider('provideStatuses')]
    public function testConstantInADataProvider(string $status, bool $expected): void
    {
        $isFinal = (new OrderController())->isFinal($status);

        self::assertSame($expected, $isFinal);
    }

    public function testConstantAsTheActualValue(): void
    {
        $status = 'CONFIRMED';

        self::assertSame('CONFIRMED', OrderController::STATUS_CONFIRMED);
        self::assertSame(actual: OrderController::STATUS_CONFIRMED, expected: $status);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'confirmed' => [OrderController::STATUS_CONFIRMED, true];
        yield 'cancelled' => [OrderController::STATUS_CANCELLED, false];
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
