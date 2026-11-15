<?php

declare(strict_types=1);

namespace Docs\StructuredAssertion\Passing;

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

        self::assertEquals(new Customer('John', 'john@example.com'), $customer);
    }
}
