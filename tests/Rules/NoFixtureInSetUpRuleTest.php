<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoFixtureInSetUpRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoFixtureInSetUpRule>
 */
final class NoFixtureInSetUpRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoFixtureInSetUpRule('warning', 'max');
    }

    public function testFlagsDoublesAndFixturesCreatedBeforeEachTest(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoFixtureInSetUp.php'], [
            ['setUp() creates a test double shared by every test of the class: the scenario of each test is hidden in setUp().', 28, 'Create it in the arrange of the tests that need it, with a helper or a factory when several do; keep setUp() for the environment (kernel, container, clock).'],
            ['setUp() creates a fixture shared by every test of the class: the scenario of each test is hidden in setUp().', 29, 'Create it in the arrange of the tests that need it, with a helper or a factory when several do; keep setUp() for the environment (kernel, container, clock).'],
            ['prepare() creates a fixture shared by every test of the class: the scenario of each test is hidden in prepare().', 36, 'Create it in the arrange of the tests that need it, with a helper or a factory when several do; keep setUp() for the environment (kernel, container, clock).'],
        ]);
    }
}
