<?php

declare(strict_types=1);

namespace Fixtures\AssertionCount;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

interface Logger
{
    public function info(string $message): void;
}

final class Order
{
    public function __construct(
        public int $id,
        public string $customer,
        public float $total,
        public string $status,
    ) {
    }
}

final class AssertionCountTest extends TestCase
{
    use ProphecyTrait;

    public function testOneStructuredAssertion(): void
    {
        $order = new Order(42, 'alice', 250.0, 'CONFIRMED');

        self::assertSame(
            ['id' => 42, 'customer' => 'alice', 'total' => 250.0, 'status' => 'CONFIRMED'],
            ['id' => $order->id, 'customer' => $order->customer, 'total' => $order->total, 'status' => $order->status],
        );
    }

    public function testTwoAssertions(): void
    {
        $order = new Order(42, 'alice', 250.0, 'CONFIRMED');

        self::assertSame(42, $order->id);
        $this->assertSame('alice', $order->customer);
    }

    public function testThreeAssertions(): void
    {
        $logger = $this->prophesize(Logger::class);
        $order = new Order(42, 'alice', 250.0, 'CONFIRMED');

        $logger->reveal()->info('processed');

        static::assertSame(42, $order->id);
        Assert::assertSame('alice', $order->customer);
        $logger->info('processed')->shouldHaveBeenCalled();
    }

    public function testFourAssertions(): void
    {
        $order = new Order(42, 'alice', 250.0, 'CONFIRMED');

        self::assertSame(42, $order->id);
        self::assertSame('alice', $order->customer);
        self::assertSame(250.0, $order->total);
        self::assertSame('CONFIRMED', $order->status);
    }

    public function testAssertionInsideArgumentThatIsNotCountedSeparately(): void
    {
        $logger = $this->prophesize(Logger::class);

        $logger->reveal()->info('processed');

        $logger->info(Argument::that(static function (string $message): bool {
            self::assertSame('processed', $message);

            return true;
        }))->shouldHaveBeenCalled();
        $logger->info('ignored')->shouldNotHaveBeenCalled();
        $logger->info(Argument::any())->shouldHaveBeenCalledTimes(1);
    }

    public function testSutMethodsNamedAssertDoNotCount(): void
    {
        $validator = new class () {
            public function assertValid(): bool
            {
                return true;
            }
        };

        self::assertTrue($validator->assertValid());
    }
}
