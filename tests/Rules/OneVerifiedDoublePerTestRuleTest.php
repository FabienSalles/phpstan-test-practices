<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\OneVerifiedDoublePerTestRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<OneVerifiedDoublePerTestRule>
 */
final class OneVerifiedDoublePerTestRuleTest extends RuleTestCase
{
    private const TIP = 'Split the test by intention, one verified double each; keep the other collaborators as stubs.';

    protected function getRule(): Rule
    {
        return new OneVerifiedDoublePerTestRule('error');
    }

    public function testFlagsTestsVerifyingSeveralDoublesWithPhpUnitOrProphecy(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/Doubles.php'], [
            ['Test testPhpUnitMocksWithTwoExpectations() verifies 2 doubles ($logger, $mailer). One intention per test: verify a single double.', 42, self::TIP],
            ['Test testProphecyMockAndSpyOnTwoDoubles() verifies 2 doubles ($logger, $mailer). One intention per test: verify a single double.', 52, self::TIP],
        ]);
    }
}
