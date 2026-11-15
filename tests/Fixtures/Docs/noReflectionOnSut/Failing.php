<?php

declare(strict_types=1);

namespace Docs\NoReflectionOnSut\Failing;

use PHPUnit\Framework\TestCase;

final class Password
{
    private string $hash;

    public function __construct(string $plain)
    {
        $this->hash = $this->hashed($plain);
    }

    public function matches(string $plain): bool
    {
        return hash_equals($this->hash, $this->hashed($plain));
    }

    private function hashed(string $plain): string
    {
        return hash('sha256', $plain);
    }
}

final class PasswordTest extends TestCase
{
    public function testHashesWithSha256(): void
    {
        $password = new Password('secret');
        $hashed = new \ReflectionMethod(Password::class, 'hashed');

        $hash = $hashed->invoke($password, 'secret');

        self::assertSame(hash('sha256', 'secret'), $hash);
    }
}
