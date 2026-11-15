<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\DontMockWhatYouDontOwnRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<DontMockWhatYouDontOwnRule>
 */
final class DontMockWhatYouDontOwnRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new DontMockWhatYouDontOwnRule(self::createReflectionProvider(), ['Doctrine\\ORM\\EntityManagerInterface'], 'error', 'max');
    }

    public function testFlagsDoublesOfDeniedThirdPartyTypes(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/DontMockWhatYouDontOwn.php'], [
            ['Test doubles Doctrine\\ORM\\EntityManagerInterface, a type the project does not own (Doctrine\\ORM\\EntityManagerInterface): the test copies how the library is called and breaks when that changes.', 23, 'Double your own port (a repository, a client interface) and cover its adapter with an integration test, or use the real implementation.'],
            ['Test doubles Doctrine\\ORM\\EntityManager, a type the project does not own (Doctrine\\ORM\\EntityManagerInterface): the test copies how the library is called and breaks when that changes.', 24, 'Double your own port (a repository, a client interface) and cover its adapter with an integration test, or use the real implementation.'],
        ]);
    }
}
