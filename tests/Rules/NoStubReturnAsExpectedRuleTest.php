<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoStubReturnAsExpectedRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoStubReturnAsExpectedRule>
 */
final class NoStubReturnAsExpectedRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoStubReturnAsExpectedRule('error', 'max');
    }

    public function testFlagsAnExpectationEqualToAStubbedReturnValue(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoStubReturnAsExpected.php'], [
            ['Test testExpectsTheStubbedVariable() expects $expected, the value a stub returns: the assertion checks the stub configuration, not the code under test.', 47, 'Assert on what the code computes from the stubbed value, or use the real collaborator (a real serializer, an in-memory repository).'],
            ['Test testExpectsTheStubbedObject() expects new \\Fixtures\\NoStubReturnAsExpected\\Product(\'book\'), the value a stub returns: the assertion checks the stub configuration, not the code under test.', 57, 'Assert on what the code computes from the stubbed value, or use the real collaborator (a real serializer, an in-memory repository).'],
        ]);
    }
}
