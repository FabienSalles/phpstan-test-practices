<?php

declare(strict_types=1);

namespace Fixtures\NoUnusedDouble;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface Mailer
{
    public function send(): void;
}

interface Logger
{
    public function info(): void;
}

final class NoUnusedDoubleTest extends TestCase
{
    use ProphecyTrait;

    public function testCreatesUnusedDoubles(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $logger = $this->prophesize(Logger::class);
        $built = $this->getMockBuilder(Mailer::class)->getMock();
        $used = $this->createStub(Mailer::class);

        $used->send();

        self::assertTrue(true);
    }

    public function testUsesItsDoubles(): void
    {
        $mailer = $this->createStub(Mailer::class);
        $logger = $this->prophesize(Logger::class);
        $send = static function () use ($mailer): void {
            $mailer->send();
        };

        $send();

        $logger->info()->shouldNotHaveBeenCalled();
    }
}
