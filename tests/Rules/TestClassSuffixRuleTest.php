<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\TestClassSuffixRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<TestClassSuffixRule>
 */
final class TestClassSuffixRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new TestClassSuffixRule('Test', 'error', 'max');
    }

    public function testFlagsAConcreteTestCaseWithoutTheSuffix(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/TestClassSuffix.php'], [
            [
                'Test class Fixtures\TestClassSuffix\OrderChecks does not end with "Test": PHPUnit only collects the files whose name ends with the suffix of the <directory> that holds them, "Test.php" by default.',
                21,
                'Rename the class and its file with the "Test" suffix.',
            ],
        ]);
    }
}
