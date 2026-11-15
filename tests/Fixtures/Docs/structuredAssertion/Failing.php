<?php

declare(strict_types=1);

namespace Docs\StructuredAssertion\Failing;

use PHPUnit\Framework\TestCase;

final readonly class Customer
{
    public function __construct(public string $name, public string $email)
    {
    }
}

final class CustomerFactory
{
    public function fromSignup(string $name, string $email): Customer
    {
        return new Customer(ucfirst($name), strtolower($email));
    }
}

final class CustomerFactoryTest extends TestCase
{
    public function testNormalisesTheSignup(): void
    {
        $customer = (new CustomerFactory())->fromSignup('john', 'John@Example.com');

        self::assertSame('John', $customer->name);
        self::assertSame('john@example.com', $customer->email);
    }
}
