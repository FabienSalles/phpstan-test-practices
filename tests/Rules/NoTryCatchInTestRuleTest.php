<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoTryCatchInTestRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoTryCatchInTestRule>
 */
final class NoTryCatchInTestRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoTryCatchInTestRule('error', 'max');
    }

    public function testFlagsACatchThatPassesWhenNothingIsThrown(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoTryCatchInTest.php'], [
            ['Test testCatchesWithoutFail() catches an exception: when nothing is thrown, the catch block never runs and the test passes anyway.', 25, 'Call $this->expectException() before the act, or end the try block with self::fail() when the caught exception itself must be inspected.'],
        ]);
    }
}
