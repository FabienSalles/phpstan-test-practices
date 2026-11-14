<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoGuardAssertionRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoGuardAssertionRule>
 */
final class NoGuardAssertionRuleTest extends RuleTestCase
{
    private const TIP = 'Read the value permissively ($result[0] ?? null, $object?->property) so the final assertion fails with a readable diff.';

    protected function getRule(): Rule
    {
        return new NoGuardAssertionRule('error', 'max');
    }

    public function testFlagsGuardsThatPrecedeTheFinalAssertion(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoGuardAssertion.php'], [
            ['Test testGuardsBeforeTheFinalAssertion() calls assertCount() as a guard before the final assertion. Drop the guard and let the final assertion show the full diff.', 24, self::TIP],
            ['Test testGuardsBeforeTheFinalAssertion() calls assertInstanceOf() as a guard before the final assertion. Drop the guard and let the final assertion show the full diff.', 25, self::TIP],
            ['Test testGuardsBeforeTheFinalAssertion() calls assertNotNull() as a guard before the final assertion. Drop the guard and let the final assertion show the full diff.', 26, self::TIP],
            ['Test testGuardsBeforeTheFinalAssertion() calls assertIsArray() as a guard before the final assertion. Drop the guard and let the final assertion show the full diff.', 27, self::TIP],
            ['Test testGuardBeforeAFinalAssertionFedByAProvider() calls assertNotEmpty() as a guard before the final assertion. Drop the guard and let the final assertion show the full diff.', 53, self::TIP],
        ]);
    }
}
