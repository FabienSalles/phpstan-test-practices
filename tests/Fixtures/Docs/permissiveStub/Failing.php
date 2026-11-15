<?php

declare(strict_types=1);

namespace Docs\PermissiveStub\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface UserRepository
{
    public function find(int $id): ?string;
}

final readonly class Greeter
{
    public function __construct(private UserRepository $users)
    {
    }

    public function greet(int $id): string
    {
        return sprintf('Hello %s', $this->users->find($id) ?? 'stranger');
    }
}

final class GreeterTest extends TestCase
{
    use ProphecyTrait;

    public function testGreetsAKnownUser(): void
    {
        $users = $this->prophesize(UserRepository::class);
        $users->find(42)->willReturn('Alice');

        $greeting = (new Greeter($users->reveal()))->greet(42);

        self::assertSame('Hello Alice', $greeting);
    }
}
