<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\SpyOverMockRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<SpyOverMockRule>
 */
final class SpyOverMockRuleTest extends RuleTestCase
{
    private const TIP = 'Stub the call, act, then verify with Prophecy ->shouldHaveBeenCalled() or by reading a hand-written double.';

    protected function getRule(): Rule
    {
        return new SpyOverMockRule('warning', 'max');
    }

    public function testFlagsVerificationsSetBeforeTheActButNotSpies(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/Doubles.php'], [
            ['Test testPhpUnitMocksWithTwoExpectations() calls expects(), which sets a verification before the act (mock). Prefer a spy verified after the act.', 45, self::TIP],
            ['Test testPhpUnitMocksWithTwoExpectations() calls expects(), which sets a verification before the act (mock). Prefer a spy verified after the act.', 47, self::TIP],
            ['Test testProphecyMockAndSpyOnTwoDoubles() calls shouldBeCalled(), which sets a verification before the act (mock). Prefer a spy verified after the act.', 55, self::TIP],
            ['Test testOnePhpUnitMockOnAProperty() calls expects(), which sets a verification before the act (mock). Prefer a spy verified after the act.', 77, self::TIP],
        ]);
    }
}
