<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoControlFlowInTestRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoControlFlowInTestRule>
 */
final class NoControlFlowInTestRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoControlFlowInTestRule('error', 'max');
    }

    public function testFlagsBranchesAndLoopsInATestBody(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoControlFlowInTest.php'], [
            ['Test testWithControlFlow() contains a foreach: a test runs one straight path, otherwise some of its branches are never checked.', 16, 'Write one test per case, use a data provider, or move the loop into a helper outside the test method.'],
            ['Test testWithControlFlow() contains an if: a test runs one straight path, otherwise some of its branches are never checked.', 20, 'Write one test per case, use a data provider, or move the loop into a helper outside the test method.'],
            ['Test testWithControlFlow() contains a match with side effects: a test runs one straight path, otherwise some of its branches are never checked.', 24, 'Write one test per case, use a data provider, or move the loop into a helper outside the test method.'],
        ]);
    }
}
