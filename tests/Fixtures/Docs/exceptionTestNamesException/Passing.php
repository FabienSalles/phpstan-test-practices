<?php

declare(strict_types=1);

namespace Docs\ExceptionTestNamesException\Passing;

use PHPUnit\Framework\TestCase;

final class MissingBirthDate extends \DomainException
{
}

final readonly class Buyer
{
    public function __construct(public ?string $birthDate)
    {
        if ($birthDate === null) {
            throw new MissingBirthDate('A buyer needs a birth date.');
        }
    }
}

final class BuyerTest extends TestCase
{
    public function testThrowsMissingBirthDateWhenTheBirthDateIsAbsent(): void
    {
        $this->expectException(MissingBirthDate::class);

        new Buyer(null);
    }
}
