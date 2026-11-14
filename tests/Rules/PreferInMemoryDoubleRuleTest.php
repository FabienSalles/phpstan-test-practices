<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Collectors\TestCaseCollector;
use FabienSalles\PHPStanTestPractices\Collectors\TestDoubleCollector;
use FabienSalles\PHPStanTestPractices\Rules\PreferInMemoryDoubleRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<PreferInMemoryDoubleRule>
 */
final class PreferInMemoryDoubleRuleTest extends RuleTestCase
{
    private const HIERARCHY = __DIR__ . '/../Fixtures/Rules/PreferInMemoryDoubleHierarchy/';
    private const TIP = 'An in-memory implementation of the interface keeps each test short and survives changes to how the dependency is called.';

    private int $threshold = 2;

    protected function getRule(): Rule
    {
        return new PreferInMemoryDoubleRule($this->threshold, 'warning');
    }

    protected function getCollectors(): array
    {
        return [new TestCaseCollector(), new TestDoubleCollector()];
    }

    public function testFlagsADependencyDoubledInSeveralTestMethods(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/PreferInMemoryDouble.php'], [
            [
                'Fixtures\PreferInMemoryDouble\OrderRepository is doubled in 3 test methods (testCountsWithAPhpUnitStub(), countsWithAProphecyStub(), testCountsWithAMockBuilder()). Prefer a hand-written in-memory stub or fake, reused across the tests.',
                38,
                self::TIP,
            ],
        ]);
    }

    public function testCountsDoublesCreatedThroughParentAndTraitHelpersAndInheritedTests(): void
    {
        $this->analyse(self::hierarchy(), [
            [
                'Fixtures\PreferInMemoryDoubleHierarchy\OrderRepository is doubled in 2 test methods (testCountsTwoOrders(), testCountsNothingByDefault()). Prefer a hand-written in-memory stub or fake, reused across the tests.',
                7,
                self::TIP,
            ],
            [
                'Fixtures\PreferInMemoryDoubleHierarchy\Mailer is doubled in 2 test methods (testSendsToAlice(), testSendsToBob()). Prefer a hand-written in-memory stub or fake, reused across the tests.',
                9,
                self::TIP,
            ],
            [
                'Fixtures\PreferInMemoryDoubleHierarchy\OrderRepository is doubled in 3 test methods (testNotifiesAboutThreeOrders(), testNotifiesThroughAHelper(), testCountsNothingByDefault()). Prefer a hand-written in-memory stub or fake, reused across the tests.',
                13,
                self::TIP,
            ],
        ]);
    }

    public function testStaysSilentOnAHierarchyBelowTheThreshold(): void
    {
        $this->threshold = 4;

        $this->analyse(self::hierarchy(), []);
    }

    /**
     * @return list<string>
     */
    private static function hierarchy(): array
    {
        return array_map(static fn (string $file): string => self::HIERARCHY . $file . '.php', [
            'Contracts',
            'OrderTestCase',
            'MailerDoubles',
            'ParentHelperTest',
            'TraitHelperTest',
            'MixedHelpersTest',
            'SingleUseTest',
        ]);
    }

    public function testStaysSilentBelowTheThreshold(): void
    {
        $this->threshold = 4;

        $this->analyse([__DIR__ . '/../Fixtures/Rules/PreferInMemoryDouble.php'], []);
    }
}
