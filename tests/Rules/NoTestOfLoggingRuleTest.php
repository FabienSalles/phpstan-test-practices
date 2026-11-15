<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoTestOfLoggingRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoTestOfLoggingRule>
 */
final class NoTestOfLoggingRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoTestOfLoggingRule(self::createReflectionProvider(), true, 'warning', 'max');
    }

    public function testFlagsChecksOnWhatIsLoggedWhenEnabled(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoTestOfLogging.php'], [
            ['Test testVerifiesTheLogger() checks what is logged: logging is rarely the behaviour under test, and the test breaks when a message is reworded.', 41, 'Keep this check only when the log is a business rule, an audited trace or a monitored metric, and ignore the line with that reason.'],
            ['Test testReadsTheLogHandler() checks what is logged: logging is rarely the behaviour under test, and the test breaks when a message is reworded.', 48, 'Keep this check only when the log is a business rule, an audited trace or a monitored metric, and ignore the line with that reason.'],
        ]);
    }
}
