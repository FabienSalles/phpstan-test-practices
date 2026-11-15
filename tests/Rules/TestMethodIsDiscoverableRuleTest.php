<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Collectors\TestCaseCollector;
use FabienSalles\PHPStanTestPractices\Collectors\TestCaseMethodCollector;
use FabienSalles\PHPStanTestPractices\Rules\TestMethodIsDiscoverableRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<TestMethodIsDiscoverableRule>
 */
final class TestMethodIsDiscoverableRuleTest extends RuleTestCase
{
    private const MESSAGE = 'Method %s() makes assertions but PHPUnit never runs it: it is not a test (test prefix, #[Test] or @test) and no test calls it.';

    protected function getRule(): Rule
    {
        return new TestMethodIsDiscoverableRule('error', 'max');
    }

    protected function getCollectors(): array
    {
        return [new TestCaseCollector(), new TestCaseMethodCollector()];
    }

    public function testFlagsAssertingMethodsThatNoTestReaches(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/TestMethodIsDiscoverable.php'], [
            [sprintf(self::MESSAGE, 'assertUnusedFromTrait'), 18, 'Make it a public test method, or call it from a test.'],
            [sprintf(self::MESSAGE, 'checksTheTotal'), 80, 'Add #[Test] or the test prefix, or call it from a test.'],
            [sprintf(self::MESSAGE, 'verifiesTheName'), 100, 'Make it a public test method, or call it from a test.'],
        ]);
    }
}
