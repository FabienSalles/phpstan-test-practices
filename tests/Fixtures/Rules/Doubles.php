<?php

declare(strict_types=1);

namespace Fixtures\Doubles;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

interface Logger
{
    public function info(string $message): void;
}

interface Mailer
{
    public function send(string $to): void;
}

final class OrderProcessor
{
    public function __construct(
        private Logger $logger,
        private Mailer $mailer,
    ) {
    }

    public function process(): void
    {
        $this->logger->info('processed');
        $this->mailer->send('alice@example.com');
    }
}

final class DoublesTest extends TestCase
{
    use ProphecyTrait;

    private Logger $logger;

    public function testPhpUnitMocksWithTwoExpectations(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('info');
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::once())->method('send');

        (new OrderProcessor($logger, $mailer))->process();
    }

    public function testProphecyMockAndSpyOnTwoDoubles(): void
    {
        $logger = $this->prophesize(Logger::class);
        $logger->info(Argument::any())->shouldBeCalled();
        $mailer = $this->prophesize(Mailer::class);

        (new OrderProcessor($logger->reveal(), $mailer->reveal()))->process();

        $mailer->send('alice@example.com')->shouldHaveBeenCalled();
    }

    public function testOneProphecySpyVerifiedAfterTheAct(): void
    {
        $logger = $this->prophesize(Logger::class);
        $mailer = $this->prophesize(Mailer::class);

        (new OrderProcessor($logger->reveal(), $mailer->reveal()))->process();

        $mailer->send('alice@example.com')->shouldHaveBeenCalled();
        $mailer->send('bob@example.com')->shouldNotHaveBeenCalled();
    }

    public function testOnePhpUnitMockOnAProperty(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->logger->expects(self::once())->method('info');

        (new OrderProcessor($this->logger, $this->createStub(Mailer::class)))->process();
    }

    public function testHandWrittenSpy(): void
    {
        $mailer = new class () implements Mailer {
            public ?string $sentTo = null;

            public function send(string $to): void
            {
                $this->sentTo = $to;
            }
        };

        (new OrderProcessor($this->createStub(Logger::class), $mailer))->process();

        self::assertSame('alice@example.com', $mailer->sentTo);
    }
}
