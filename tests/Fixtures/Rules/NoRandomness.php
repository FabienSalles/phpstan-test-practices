<?php

declare(strict_types=1);

namespace Fixtures\NoRandomness;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class NoRandomnessTest extends TestCase
{
    public function testDrawsRandomValues(): void
    {
        $amount = random_int(1, 100);
        $id = Uuid::uuid4();
        $name = Factory::create()->name();

        self::assertSame([$amount, $id, $name], [$amount, $id, $name]);
    }

    public function testSeedsFaker(): void
    {
        $faker = Factory::create();
        $faker->seed(1234);

        $name = $faker->name();

        self::assertSame($name, $name);
    }

    public function testUsesFixedValues(): void
    {
        $amount = 42;

        self::assertSame(42, $amount);
    }
}
