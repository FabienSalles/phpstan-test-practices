<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoTestCaseConstructorRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoTestCaseConstructorRule>
 */
final class NoTestCaseConstructorRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoTestCaseConstructorRule('error', 'max');
    }

    public function testFlagsAConstructorInATestCase(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoTestCaseConstructor.php'], [
            ['A test case declares a constructor: PHPUnit creates one instance per test with its own arguments, and the constructor runs for every test, before setUp().', 13, 'Build what a test needs in its arrange, or the environment in setUp().'],
        ]);
    }
}
