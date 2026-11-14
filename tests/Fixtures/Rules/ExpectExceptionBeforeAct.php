<?php

declare(strict_types=1);

namespace Fixtures\ExpectExceptionBeforeAct;

use PHPUnit\Framework\TestCase;

final class Account
{
    public string $status = 'OPEN';

    public function close(): void
    {
        throw new \LogicException('Already closed');
    }
}

final class ExpectExceptionBeforeActTest extends TestCase
{
    public function testAssertsAfterTheThrowingAct(): void
    {
        $account = new Account();
        $this->expectException(\LogicException::class);

        $account->close();
        self::assertSame('CLOSED', $account->status);
    }

    public function testEndsWithTheAct(): void
    {
        $account = new Account();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Already closed');

        $account->close();
    }
}
