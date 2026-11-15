<?php

declare(strict_types=1);

namespace Fixtures\ExceptionTestNamesException;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MissingBirthDate extends \RuntimeException
{
}

final class CannotSuspendBeneficiaryException extends \RuntimeException
{
}

final class ExceptionTestNamesExceptionTest extends TestCase
{
    public function testThrowsWhenTheDateIsMissing(): void
    {
        $this->expectException(MissingBirthDate::class);

        throw new MissingBirthDate();
    }

    public function testThrowsMissingBirthDateWhenTheDateIsMissing(): void
    {
        $this->expectException(MissingBirthDate::class);

        throw new MissingBirthDate();
    }

    public function testRefusesToSuspendBeneficiary(): void
    {
        $this->expectException(CannotSuspendBeneficiaryException::class);

        throw new CannotSuspendBeneficiaryException();
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideExceptions')]
    public function testThrowsTheProvidedException(string $exception): void
    {
        $this->expectException($exception);

        throw new $exception();
    }

    /**
     * @return iterable<string, array{class-string<\Throwable>}>
     */
    public static function provideExceptions(): iterable
    {
        yield 'missing birth date' => [MissingBirthDate::class];
    }
}
