<?php

declare(strict_types=1);

namespace Docs\AssertionCount\Failing;

use PHPUnit\Framework\TestCase;

final readonly class Customer
{
    public function __construct(public string $name, public string $email, public string $country, public bool $vip)
    {
    }
}

final class CustomerFactory
{
    public function fromSignup(string $name, string $email): Customer
    {
        return new Customer(ucfirst($name), strtolower($email), 'FR', false);
    }
}

final class CustomerFactoryTest extends TestCase
{
    public function testCreatesACustomerFromASignup(): void
    {
        $customer = (new CustomerFactory())->fromSignup('alice', 'Alice@Example.com');

        self::assertSame('Alice', $customer->name);
        self::assertSame('alice@example.com', $customer->email);
        self::assertSame('FR', $customer->country);
        self::assertFalse($customer->vip);
    }
}
