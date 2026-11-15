<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\AssertionCountRule;
use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<AssertionCountRule>
 */
final class AssertionCountRuleTest extends RuleTestCase
{
    private const FIXTURE = __DIR__ . '/../Fixtures/Rules/AssertionCount.php';
    private const TIP = 'Compare every expected field at once: self::assertSame([\'status\' => \'CONFIRMED\', \'total\' => 120.0], [\'status\' => $order->status, \'total\' => $order->total]).';

    protected function getRule(): Rule
    {
        return new AssertionCountRule(3, 4, 'max');
    }

    public function testReportsThreeAssertionsOrMore(): void
    {
        $this->analyse([self::FIXTURE], [
            ['Test testThreeAssertions() makes 3 assertions. Prefer one structured assertion, or split the test by intention.', 50, self::TIP],
            ['Test testFourAssertions() makes 4 assertions. Prefer one structured assertion, or split the test by intention.', 62, self::TIP],
            ['Test testAssertionInsideArgumentThatIsNotCountedSeparately() makes 3 assertions. Prefer one structured assertion, or split the test by intention.', 72, self::TIP],
        ]);
    }

    public function testGradesThreeAssertionsAsAWarningAndFourAsAnError(): void
    {
        $severities = array_map(
            static fn (Error $error): array => [$error->getLine(), $error->getMetadata()],
            $this->gatherAnalyserErrors([self::FIXTURE]),
        );

        self::assertSame([
            [50, ['testPracticesSeverity' => 'warning']],
            [62, ['testPracticesSeverity' => 'error']],
            [72, ['testPracticesSeverity' => 'warning']],
        ], $severities);
    }
}
