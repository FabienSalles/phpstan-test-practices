<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoReflectionOnSutRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoReflectionOnSutRule>
 */
final class NoReflectionOnSutRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoReflectionOnSutRule('error', 'max');
    }

    public function testFlagsReflectionReachingPrivateState(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoReflectionOnSut.php'], [
            ['The test calls setAccessible() to reach private state: it is coupled to how the class is written, not to what it does.', 34, 'Test the behaviour through the public methods that use this state; if it cannot be reached, it may deserve its own class.'],
            ['The test calls invoke() to reach private state: it is coupled to how the class is written, not to what it does.', 36, 'Test the behaviour through the public methods that use this state; if it cannot be reached, it may deserve its own class.'],
            ['The test calls getValue() to reach private state: it is coupled to how the class is written, not to what it does.', 46, 'Test the behaviour through the public methods that use this state; if it cannot be reached, it may deserve its own class.'],
            ['The test calls Closure::bind() to reach private state: it is coupled to how the class is written, not to what it does.', 55, 'Test the behaviour through the public methods that use this state; if it cannot be reached, it may deserve its own class.'],
        ]);
    }
}
