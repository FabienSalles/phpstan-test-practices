<?php

declare(strict_types=1);

namespace Fixtures\PreferInMemoryDoubleHierarchy;

final class SingleUseTest extends OrderTestCase
{
    use MailerDoubles;

    private Mailer $mailer;

    protected function setUp(): void
    {
        $this->mailer = $this->mailer();
    }

    public function testSendsOnce(): void
    {
        $this->mailer->send('alice@example.com');

        self::assertTrue(true);
    }

    public function testCountsWithAHandWrittenRepository(): void
    {
        $repository = new class () implements OrderRepository {
            public function count(): int
            {
                return 4;
            }
        };

        self::assertSame(4, $repository->count());
    }
}
