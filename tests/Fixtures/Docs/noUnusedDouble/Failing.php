<?php

declare(strict_types=1);

namespace Docs\NoUnusedDouble\Failing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface Mailer
{
    public function send(string $to): void;
}

interface Clock
{
    public function now(): \DateTimeImmutable;
}

final readonly class Welcome
{
    public function __construct(private Mailer $mailer)
    {
    }

    public function welcome(string $email): void
    {
        $this->mailer->send($email);
    }
}

final class WelcomeTest extends TestCase
{
    use ProphecyTrait;

    public function testEmailsTheNewUser(): void
    {
        $mailer = $this->prophesize(Mailer::class);
        $clock = $this->createStub(Clock::class);

        (new Welcome($mailer->reveal()))->welcome('alice@example.com');

        $mailer->send('alice@example.com')->shouldHaveBeenCalled();
    }
}
