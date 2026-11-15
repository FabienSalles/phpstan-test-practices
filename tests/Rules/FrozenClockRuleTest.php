<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\FrozenClockRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FrozenClockRule>
 */
final class FrozenClockRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FrozenClockRule('error', 'max');
    }

    public function testFlagsReadsOfTheSystemClock(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/FrozenClock.php'], [
            ['testReadsTheSystemClock() reads the system clock: the test depends on when it runs.', 14, 'Use an absolute date (new \\DateTimeImmutable(\'2026-11-12 09:00\')) and inject a frozen clock (Symfony MockClock, a FrozenClock) into the code under test.'],
            ['testReadsTheSystemClock() reads the system clock: the test depends on when it runs.', 15, 'Use an absolute date (new \\DateTimeImmutable(\'2026-11-12 09:00\')) and inject a frozen clock (Symfony MockClock, a FrozenClock) into the code under test.'],
            ['testReadsTheSystemClock() reads the system clock: the test depends on when it runs.', 16, 'Use an absolute date (new \\DateTimeImmutable(\'2026-11-12 09:00\')) and inject a frozen clock (Symfony MockClock, a FrozenClock) into the code under test.'],
            ['testReadsTheSystemClock() reads the system clock: the test depends on when it runs.', 17, 'Use an absolute date (new \\DateTimeImmutable(\'2026-11-12 09:00\')) and inject a frozen clock (Symfony MockClock, a FrozenClock) into the code under test.'],
            ['provideDates() reads the system clock: the test depends on when it runs.', 36, 'Use an absolute date (new \\DateTimeImmutable(\'2026-11-12 09:00\')) and inject a frozen clock (Symfony MockClock, a FrozenClock) into the code under test.'],
        ]);
    }
}
