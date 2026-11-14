<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\AssertionsLastRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<AssertionsLastRule>
 */
final class AssertionsLastRuleTest extends RuleTestCase
{
    private const HIDDEN_TIP = 'Let the double record what it receives, then assert on the recorded value in the final block.';

    protected function getRule(): Rule
    {
        return new AssertionsLastRule('error');
    }

    public function testFlagsStatementsAfterAnAssertionAndAssertionsHiddenInTheArrange(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/AssertionsLast.php'], [
            [
                'Test testAssertionInTheMiddle() has a statement after an assertion. Assertions come last, in a single final block.',
                25,
                'Move this statement before the first assertion, or split the test if it checks two behaviours.',
            ],
            [
                'Test assertionHiddenInCallback() calls assertSame() inside an arrange callback, closure or anonymous class. Verify after the act, on the result or on a spy.',
                35,
                self::HIDDEN_TIP,
            ],
            [
                'Test testAssertionHiddenInAnonymousClassSpy() calls assertSame() inside an arrange callback, closure or anonymous class. Verify after the act, on the result or on a spy.',
                46,
                self::HIDDEN_TIP,
            ],
        ]);
    }
}
