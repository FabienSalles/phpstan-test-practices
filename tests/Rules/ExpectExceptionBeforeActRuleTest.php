<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\ExpectExceptionBeforeActRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ExpectExceptionBeforeActRule>
 */
final class ExpectExceptionBeforeActRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ExpectExceptionBeforeActRule('error', 'max');
    }

    public function testFlagsStatementsAfterTheThrowingAct(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/ExpectExceptionBeforeAct.php'], [
            ['Test testAssertsAfterTheThrowingAct() has statements after the act that follows expectException(): the act throws, so they never run.', 27, 'Make the act the last statement after expectException(); move what must be checked into another test.'],
        ]);
    }
}
