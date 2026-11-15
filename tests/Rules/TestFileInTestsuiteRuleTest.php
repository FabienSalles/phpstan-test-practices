<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\TestFileInTestsuiteRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<TestFileInTestsuiteRule>
 */
final class TestFileInTestsuiteRuleTest extends RuleTestCase
{
    private const PROJECT = __DIR__ . '/../Fixtures/Rules/TestFileInTestsuite';
    private const MESSAGE = 'Test class Fixtures\TestFileInTestsuite\%s is in a file that no <testsuite> of phpunit.xml.dist includes: PHPUnit never runs it.';
    private const TIP = 'Move the file under a <directory> of a test suite (with its suffix and prefix), add a <file> entry, or remove it from <exclude>.';

    private ?string $configuration = null;
    private string $workingDirectory = self::PROJECT;

    protected function getRule(): Rule
    {
        return new TestFileInTestsuiteRule($this->configuration, $this->workingDirectory, 'error', 'max');
    }

    public function testFlagsTheTestClassesOutsideEveryTestSuiteOfTheDetectedConfiguration(): void
    {
        $this->analyse(self::files(), [
            [sprintf(self::MESSAGE, 'ForgottenTest'), 9, self::TIP],
            [sprintf(self::MESSAGE, 'OrderTest'), 9, self::TIP],
            [sprintf(self::MESSAGE, 'ExcludedTest'), 9, self::TIP],
        ]);
    }

    public function testReadsAnExplicitConfiguration(): void
    {
        $this->configuration = self::PROJECT . '/phpunit.xml.dist';
        $this->workingDirectory = __DIR__;

        $this->analyse([self::PROJECT . '/tests/Legacy/ForgottenTest.php'], [
            [sprintf(self::MESSAGE, 'ForgottenTest'), 9, self::TIP],
        ]);
    }

    public function testStaysSilentWithoutConfiguration(): void
    {
        $this->workingDirectory = __DIR__;

        $this->analyse(self::files(), []);
    }

    /**
     * @return list<string>
     */
    private static function files(): array
    {
        return array_map(static fn (string $file): string => self::PROJECT . '/tests/' . $file, [
            'Unit/BaseTestCase.php',
            'Unit/IncludedTest.php',
            'Unit/ExcludedTest.php',
            'Legacy/ForgottenTest.php',
            'Misc/ListedChecks.php',
            'Misc/OrderSpec.php',
            'Misc/OrderTest.php',
        ]);
    }
}
