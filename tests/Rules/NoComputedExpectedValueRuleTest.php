<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoComputedExpectedValueRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoComputedExpectedValueRule>
 */
final class NoComputedExpectedValueRuleTest extends RuleTestCase
{
    private const TIP = 'Hard-code the expected value of each case, for instance as a data provider column.';

    protected function getRule(): Rule
    {
        return new NoComputedExpectedValueRule('error', 'max');
    }

    public function testFlagsExpectedValuesComputedInTheTest(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoComputedExpectedValue.php'], [
            ['Test testExpectedValueComputedWithMatch() computes its expected value (match expression). An expectation recomputed in the test mirrors the implementation; write the literal expected result.', 30, self::TIP],
            ['Test testExpectedValueComputedInline() computes its expected value (arithmetic "*"). An expectation recomputed in the test mirrors the implementation; write the literal expected result.', 37, self::TIP],
            ['Test testExpectedValueComputedWithTernary() computes its expected value (ternary expression). An expectation recomputed in the test mirrors the implementation; write the literal expected result.', 46, self::TIP],
        ]);
    }
}
