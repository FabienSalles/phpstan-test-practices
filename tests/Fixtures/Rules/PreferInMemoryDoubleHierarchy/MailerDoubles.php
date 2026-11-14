<?php

declare(strict_types=1);

namespace Fixtures\PreferInMemoryDoubleHierarchy;

trait MailerDoubles
{
    private function mailer(): Mailer
    {
        return $this->createStub(Mailer::class);
    }
}
