<?php

declare(strict_types=1);

namespace Fixtures\PreferInMemoryDoubleHierarchy;

use PHPUnit\Framework\TestCase;

final class TraitHelperTest extends TestCase
{
    use MailerDoubles;

    public function testSendsToAlice(): void
    {
        $mailer = $this->mailer();

        $mailer->send('alice@example.com');

        self::assertTrue(true);
    }

    public function testSendsToBob(): void
    {
        $mailer = $this->mailer();

        $mailer->send('bob@example.com');

        self::assertTrue(true);
    }
}
