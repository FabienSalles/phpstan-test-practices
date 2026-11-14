<?php

declare(strict_types=1);

namespace Fixtures\PreferInMemoryDoubleHierarchy;

interface OrderRepository
{
    public function count(): int;
}

interface Mailer
{
    public function send(string $to): void;
}
