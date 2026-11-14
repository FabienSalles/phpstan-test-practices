<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices;

final class Level
{
    public const MAX = 'max';

    public static function enables(int|string $configured, int $required): bool
    {
        return $configured === self::MAX || $configured >= $required;
    }
}
