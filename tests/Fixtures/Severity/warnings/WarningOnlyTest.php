<?php

declare(strict_types=1);

namespace Fixtures\Severity\Warnings;

use PHPUnit\Framework\TestCase;

interface Logger
{
    public function info(string $message): void;
}

final class WarningOnlyTest extends TestCase
{
    public function testLogsWithAMock(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('info');

        $logger->info('processed');
    }
}
