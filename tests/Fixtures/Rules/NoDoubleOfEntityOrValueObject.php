<?php

declare(strict_types=1);

namespace Fixtures\NoDoubleOfEntityOrValueObject;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[ORM\Entity]
class Order
{
}

/**
 * @ORM\Embeddable
 */
class Address
{
}

readonly class Money
{
    public function __construct(public int $amount)
    {
    }
}

class Email
{
}

interface OrderRepository
{
    public function find(int $id): ?Order;
}

final class NoDoubleOfEntityOrValueObjectTest extends TestCase
{
    use ProphecyTrait;

    public function testDoublesPlainObjects(): void
    {
        $order = $this->createMock(Order::class);
        $address = $this->prophesize(Address::class);
        $money = $this->createStub(Money::class);
        $email = $this->createStub(Email::class);
        $repository = $this->createStub(OrderRepository::class);

        self::assertSame([$order, $address, $money, $email, $repository], [$order, $address, $money, $email, $repository]);
    }
}
