<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\DataProviderNamedKeysRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<DataProviderNamedKeysRule>
 */
final class DataProviderNamedKeysRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new DataProviderNamedKeysRule('error', 'max');
    }

    public function testFlagsUnnamedDataSets(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/DataProviderNamedKeys.php'], [
            ['Data provider provideUnnamed() yields a data set without a name: PHPUnit reports it as "data set #0".', 37, 'Give each data set a string key that names the case: yield \'0%, not eligible\' => [...].'],
            ['Data provider provideReturnedArray() yields a data set without a name: PHPUnit reports it as "data set #0".', 48, 'Give each data set a string key that names the case: yield \'0%, not eligible\' => [...].'],
        ]);
    }
}
