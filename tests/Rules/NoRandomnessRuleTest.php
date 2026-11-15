<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\NoRandomnessRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoRandomnessRule>
 */
final class NoRandomnessRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoRandomnessRule('error', 'max');
    }

    public function testFlagsRandomValuesButNotASeededFaker(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/NoRandomness.php'], [
            ['testDrawsRandomValues() draws a random value with random_int(): the test may behave differently on every run.', 15, 'Use fixed values, or inject a generator returning a fixed sequence; seed Faker with $faker->seed(...) when you need it.'],
            ['testDrawsRandomValues() draws a random value with Uuid::uuid4(): the test may behave differently on every run.', 16, 'Use fixed values, or inject a generator returning a fixed sequence; seed Faker with $faker->seed(...) when you need it.'],
            ['testDrawsRandomValues() draws a random value with Factory::create(): the test may behave differently on every run.', 17, 'Use fixed values, or inject a generator returning a fixed sequence; seed Faker with $faker->seed(...) when you need it.'],
        ]);
    }
}
