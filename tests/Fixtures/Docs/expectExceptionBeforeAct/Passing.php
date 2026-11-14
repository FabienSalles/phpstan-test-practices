<?php

declare(strict_types=1);

namespace Docs\ExpectExceptionBeforeAct\Passing;

use PHPUnit\Framework\TestCase;

final class Account
{
    private string $status = 'OPEN';

    public function close(): void
    {
        if ($this->status === 'CLOSED') {
            throw new \LogicException('The account is already closed.');
        }

        $this->status = 'CLOSED';
    }

    public function status(): string
    {
        return $this->status;
    }
}

final class AccountTest extends TestCase
{
    public function testRefusesToCloseTwice(): void
    {
        $account = new Account();
        $account->close();
        $this->expectException(\LogicException::class);

        $account->close();
    }

    public function testClosesAnOpenAccount(): void
    {
        $account = new Account();

        $account->close();

        self::assertSame('CLOSED', $account->status());
    }
}
