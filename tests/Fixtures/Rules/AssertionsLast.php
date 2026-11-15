<?php

declare(strict_types=1);

namespace Fixtures\AssertionsLast;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

interface Mailer
{
    public function send(string $to): void;
}

final class AssertionsLastTest extends TestCase
{
    use ProphecyTrait;

    public function testAssertionInTheMiddle(): void
    {
        $value = [1];
        self::assertSame([1], $value);
        $value[] = 2;

        self::assertSame([1, 2], $value);
    }

    #[Test]
    public function assertionHiddenInCallback(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('send')->willReturnCallback(function (string $to): void {
            $this->assertSame('alice@example.com', $to);
        });

        $mailer->send('alice@example.com');
    }

    public function testAssertionHiddenInAnonymousClassSpy(): void
    {
        $mailer = new class () implements Mailer {
            public function send(string $to): void
            {
                TestCase::assertSame('alice@example.com', $to);
            }
        };

        $mailer->send('alice@example.com');
    }

    public function testAnonymousClassSpyRecordingThenAsserting(): void
    {
        $mailer = new class () implements Mailer {
            public ?string $sentTo = null;

            public function send(string $to): void
            {
                $this->sentTo = $to;
            }
        };

        $mailer->send('alice@example.com');

        self::assertSame('alice@example.com', $mailer->sentTo);
    }

    public function testProphecySpyWithArgumentThatAfterTheAct(): void
    {
        $mailer = $this->prophesize(Mailer::class);

        $mailer->reveal()->send('alice@example.com');

        $mailer->send(Argument::that(static function (string $to): bool {
            self::assertSame('alice@example.com', $to);

            return true;
        }))->shouldHaveBeenCalled();
    }

    public function testExpectedExceptionBeforeTheAct(): void
    {
        $this->expectException(\LogicException::class);

        throw new \LogicException();
    }

    public function helperIsNotATest(): void
    {
        self::assertTrue(true);
        $value = 1;
    }
}
