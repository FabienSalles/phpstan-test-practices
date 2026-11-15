<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\PermissiveStubRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<PermissiveStubRule>
 */
final class PermissiveStubRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new PermissiveStubRule('warning', 'max');
    }

    public function testFlagsStubsThatConstrainTheirArguments(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/PermissiveStub.php'], [
            ['The stub of $repository->get() constrains its arguments: a stub provides data, it does not check how it is called.', 25, 'Use Argument::any() or Argument::cetera() in a stub; check the arguments with a spy verification when they matter.'],
            ['The stub configured with with() constrains its arguments: a stub provides data, it does not check how it is called.', 27, 'Drop with() from the stub; check the arguments with a spy verification when they matter.'],
        ]);
    }
}
