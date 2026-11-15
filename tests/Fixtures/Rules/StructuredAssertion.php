<?php

declare(strict_types=1);

namespace Fixtures\StructuredAssertion;

use PHPUnit\Framework\TestCase;

final readonly class Customer
{
    public function __construct(public string $name, public string $email)
    {
    }

    public function isVip(): bool
    {
        return false;
    }
}

final class StructuredAssertionTest extends TestCase
{
    public function testChecksTwoFields(): void
    {
        $customer = new Customer('John', 'john@example.com');

        self::assertSame('John', $customer->name);
        self::assertSame('john@example.com', $customer->email);
    }

    public function testChecksTwoKeys(): void
    {
        $payload = ['id' => 1, 'status' => 'OK'];

        self::assertSame(1, $payload['id']);
        self::assertSame('OK', $payload['status']);
    }

    public function testChecksAFieldAndAGetter(): void
    {
        $customer = new Customer('John', 'john@example.com');

        self::assertSame('John', $customer->name);
        self::assertFalse($customer->isVip());
    }

    public function testChecksTwoValues(): void
    {
        $customer = new Customer('John', 'john@example.com');
        $other = new Customer('Jane', 'jane@example.com');

        self::assertSame('John', $customer->name);
        self::assertSame('Jane', $other->name);
    }

    public function testLeavesLongerTestsToAssertionCount(): void
    {
        $customer = new Customer('John', 'john@example.com');

        self::assertSame('John', $customer->name);
        self::assertSame('john@example.com', $customer->email);
        self::assertFalse($customer->isVip());
    }
}
