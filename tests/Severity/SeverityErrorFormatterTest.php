<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Severity;

use PHPUnit\Framework\TestCase;

final class SeverityErrorFormatterTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../Fixtures/Severity';

    public function testWarningsAloneDoNotFailTheAnalysis(): void
    {
        [$exitCode, $output] = self::phpstan('phpstan.neon', 'warnings');

        self::assertSame(
            ['exitCode' => 0, 'summary' => true],
            ['exitCode' => $exitCode, 'summary' => str_contains($output, 'Test practices: 0 blocking error(s), 1 non-blocking warning(s).')],
        );
    }

    public function testAnErrorFailsTheAnalysisEvenNextToWarnings(): void
    {
        [$exitCode, $output] = self::phpstan('phpstan.neon', 'warnings', 'errors');

        self::assertSame(
            ['exitCode' => 1, 'summary' => true],
            ['exitCode' => $exitCode, 'summary' => str_contains($output, 'Test practices: 1 blocking error(s), 1 non-blocking warning(s).')],
        );
    }

    public function testARuleRaisedToErrorInTheConfigurationFailsTheAnalysis(): void
    {
        [$exitCode] = self::phpstan('spy-over-mock-as-error.neon', 'warnings');

        self::assertSame(1, $exitCode);
    }

    public function testTheInnerFormatterIsConfigurable(): void
    {
        [$exitCode, $output] = self::phpstan('json.neon', 'warnings');

        self::assertSame(
            ['exitCode' => 0, 'file_errors' => 1],
            ['exitCode' => $exitCode, 'file_errors' => json_decode($output, true, flags: JSON_THROW_ON_ERROR)['totals']['file_errors'] ?? null],
        );
    }

    /**
     * @return array{int, string}
     */
    private static function phpstan(string $config, string ...$paths): array
    {
        $command = sprintf(
            'cd %s && %s %s analyse --no-progress --no-interaction --memory-limit=512M -c %s %s 2>/dev/null',
            escapeshellarg(self::FIXTURES),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/../../vendor/bin/phpstan'),
            escapeshellarg(self::FIXTURES . '/' . $config),
            implode(' ', array_map(static fn (string $path): string => escapeshellarg(self::FIXTURES . '/' . $path), $paths)),
        );
        exec($command, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
