<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\ProphecyOverCreateMockCallbackRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ProphecyOverCreateMockCallbackRule>
 */
final class ProphecyOverCreateMockCallbackRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ProphecyOverCreateMockCallbackRule(self::createReflectionProvider(), 'warning', 'max');
    }

    public function testFlagsAScriptedPhpUnitDoubleProphecyCouldReplace(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/ProphecyOverCreateMockCallback.php'], [
            ['Test testScriptsAMockOfAnInterface() scripts a PHPUnit double with willReturnCallback(): a double that needs behaviour reads better as a Prophecy double or a hand-written one.', 27, 'Use $this->prophesize() with ->will(...) or ->willReturn(...) per argument, or write a small in-memory implementation.'],
        ]);
    }
}
