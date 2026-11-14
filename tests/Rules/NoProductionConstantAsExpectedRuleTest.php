<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoProductionConstantAsExpectedRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoProductionConstantAsExpectedRule>
 */
final class NoProductionConstantAsExpectedRuleTest extends RuleTestCase
{
    private const MESSAGE = 'Expected value Fixtures\NoProductionConstantAsExpected\OrderController::%s is a production constant: the assertion compares the constant with itself. Write the literal value instead.';
    private const TIP = 'If the constant itself is the contract under test, keep it and add "// @phpstan-ignore testPractices.noProductionConstantAsExpected" on this line.';

    protected function getRule(): Rule
    {
        return new NoProductionConstantAsExpectedRule(self::createReflectionProvider(), 'error', 'max');
    }

    public function testFlagsProductionConstantsOnlyInTheExpectedPositionOfAnAssertion(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoProductionConstantAsExpected.php'], [
            [sprintf(self::MESSAGE, 'STATUS_CONFIRMED'), 51, self::TIP],
            [sprintf(self::MESSAGE, 'STATUS_CONFIRMED'), 58, self::TIP],
            [sprintf(self::MESSAGE, 'STATUS_CANCELLED'), 65, self::TIP],
            [sprintf(self::MESSAGE, 'STATUS_CONFIRMED'), 72, self::TIP],
        ]);
    }
}
