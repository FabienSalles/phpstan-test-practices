<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoDependsBetweenTestsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoDependsBetweenTestsRule>
 */
final class NoDependsBetweenTestsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDependsBetweenTestsRule('error', 'max');
    }

    public function testFlagsTestsDependingOnAnother(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoDependsBetweenTests.php'], [
            ['Test testPushesWithAnAttribute() depends on another test: it cannot run alone, and it is skipped when the other one fails.', 24, 'Build what the test needs in its own arrange, with a helper or a factory shared by both tests.'],
            ['Test testPushesWithAnAnnotation() depends on another test: it cannot run alone, and it is skipped when the other one fails.', 36, 'Build what the test needs in its own arrange, with a helper or a factory shared by both tests.'],
        ]);
    }
}
