<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoUnusedDoubleRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoUnusedDoubleRule>
 */
final class NoUnusedDoubleRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoUnusedDoubleRule('error', 'max');
    }

    public function testFlagsDoublesNeverRead(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoUnusedDouble.php'], [
            ['Test testCreatesUnusedDoubles() creates the double $mailer and never uses it.', 26, 'Remove it, or pass it to the code under test if the test forgot to.'],
            ['Test testCreatesUnusedDoubles() creates the double $logger and never uses it.', 27, 'Remove it, or pass it to the code under test if the test forgot to.'],
            ['Test testCreatesUnusedDoubles() creates the double $built and never uses it.', 28, 'Remove it, or pass it to the code under test if the test forgot to.'],
        ]);
    }
}
