<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoDoubleOfEntityOrValueObjectRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoDoubleOfEntityOrValueObjectRule>
 */
final class NoDoubleOfEntityOrValueObjectRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDoubleOfEntityOrValueObjectRule(self::createReflectionProvider(), ['*\\Email'], 'error', 'max');
    }

    public function testFlagsDoublesOfEntitiesReadonlyClassesAndConfiguredPatterns(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoDoubleOfEntityOrValueObject.php'], [
            ['Test doubles Fixtures\\NoDoubleOfEntityOrValueObject\\Order, a Doctrine entity or embeddable: a plain object without I/O is built, not doubled.', 45, 'Create the real object, with a factory or an object mother when building it takes several lines.'],
            ['Test doubles Fixtures\\NoDoubleOfEntityOrValueObject\\Address, a Doctrine entity or embeddable: a plain object without I/O is built, not doubled.', 46, 'Create the real object, with a factory or an object mother when building it takes several lines.'],
            ['Test doubles Fixtures\\NoDoubleOfEntityOrValueObject\\Money, a readonly class: a plain object without I/O is built, not doubled.', 47, 'Create the real object, with a factory or an object mother when building it takes several lines.'],
            ['Test doubles Fixtures\\NoDoubleOfEntityOrValueObject\\Email, which matches *\\Email: a plain object without I/O is built, not doubled.', 48, 'Create the real object, with a factory or an object mother when building it takes several lines.'],
        ]);
    }
}
