<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoKernelForPureFunctionRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoKernelForPureFunctionRule>
 */
final class NoKernelForPureFunctionRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoKernelForPureFunctionRule('warning', 'max');
    }

    public function testFlagsAKernelTestCaseThatNeverBootsTheKernel(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoKernelForPureFunction.php'], [
            ['Test class Fixtures\\NoKernelForPureFunction\\SlugTest extends Symfony\\Bundle\\FrameworkBundle\\Test\\KernelTestCase but never boots the kernel: a test of plain objects is a TestCase.', 10, 'Extend PHPUnit\\Framework\\TestCase: the test runs faster and does not depend on the container configuration.'],
        ]);
    }
}
