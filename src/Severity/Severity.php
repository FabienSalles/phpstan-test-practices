<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Severity;

use PHPStan\Analyser\Error;

final class Severity
{
    public const ERROR = 'error';
    public const WARNING = 'warning';
    private const METADATA_KEY = 'testPracticesSeverity';

    /**
     * @return array{testPracticesSeverity: string}
     */
    public static function metadata(string $severity): array
    {
        return [self::METADATA_KEY => $severity];
    }

    public static function isWarning(Error $error): bool
    {
        return ($error->getMetadata()[self::METADATA_KEY] ?? null) === self::WARNING;
    }
}
