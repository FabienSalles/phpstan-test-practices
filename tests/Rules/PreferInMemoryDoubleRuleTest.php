<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\PreferInMemoryDoubleRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<PreferInMemoryDoubleRule>
 */
final class PreferInMemoryDoubleRuleTest extends RuleTestCase
{
    private int $threshold = 2;

    protected function getRule(): Rule
    {
        return new PreferInMemoryDoubleRule($this->threshold, 'warning');
    }

    public function testFlagsADependencyDoubledInSeveralTestMethods(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/PreferInMemoryDouble.php'], [
            [
                'Fixtures\PreferInMemoryDouble\OrderRepository is doubled in 3 test methods (testCountsWithAPhpUnitStub(), countsWithAProphecyStub(), testCountsWithAMockBuilder()). Prefer a hand-written in-memory stub or fake, reused across the tests.',
                38,
                'An in-memory implementation of the interface keeps each test short and survives changes to how the dependency is called.',
            ],
        ]);
    }

    public function testStaysSilentBelowTheThreshold(): void
    {
        $this->threshold = 4;

        $this->analyse([__DIR__ . '/../Fixtures/Rules/PreferInMemoryDouble.php'], []);
    }
}
