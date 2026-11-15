<?php

declare(strict_types=1);

namespace Symfony\Bundle\FrameworkBundle\Test {
    abstract class KernelTestCase extends \PHPUnit\Framework\TestCase
    {
        protected static function bootKernel(): void
        {
        }
    }

    abstract class WebTestCase extends KernelTestCase
    {
        protected static function createClient(): object
        {
            return new \stdClass();
        }

        public static function assertResponseStatusCodeSame(int $expectedCode, string $message = ''): void
        {
        }

        public static function assertSelectorTextContains(string $selector, string $text, string $message = ''): void
        {
        }
    }
}

namespace Spatie\Snapshots {
    trait MatchesSnapshots
    {
    }
}
