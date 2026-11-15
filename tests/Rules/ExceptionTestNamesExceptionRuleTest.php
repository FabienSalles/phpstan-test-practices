<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\ExceptionTestNamesExceptionRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ExceptionTestNamesExceptionRule>
 */
final class ExceptionTestNamesExceptionRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ExceptionTestNamesExceptionRule('error', 'max');
    }

    public function testFlagsAnExceptionTestThatDoesNotNameTheException(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/ExceptionTestNamesException.php'], [
            ['Test testThrowsWhenTheDateIsMissing() expects MissingBirthDate but its name does not say so: the name should tell which error the test is about.', 22, 'Name the test after the exception, for instance itThrowsMissingBirthDateWhen...().'],
        ]);
    }
}
