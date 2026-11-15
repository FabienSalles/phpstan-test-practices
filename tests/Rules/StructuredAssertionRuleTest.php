<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\StructuredAssertionRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<StructuredAssertionRule>
 */
final class StructuredAssertionRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new StructuredAssertionRule('warning', 'max');
    }

    public function testFlagsConsecutiveAssertionsOnFieldsOfTheSameValue(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/StructuredAssertion.php'], [
            ['Test testChecksTwoFields() checks $customer field by field: one assertion on the whole value shows every difference at once.', 28, 'Compare the whole object with assertEquals(new X(...), $x), or the fields at once with assertSame([...], [...]).'],
            ['Test testChecksTwoKeys() checks $payload field by field: one assertion on the whole value shows every difference at once.', 36, 'Compare the whole object with assertEquals(new X(...), $x), or the fields at once with assertSame([...], [...]).'],
            ['Test testChecksAFieldAndAGetter() checks $customer field by field: one assertion on the whole value shows every difference at once.', 44, 'Compare the whole object with assertEquals(new X(...), $x), or the fields at once with assertSame([...], [...]).'],
        ]);
    }
}
