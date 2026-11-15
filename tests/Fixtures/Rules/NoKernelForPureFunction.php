<?php

declare(strict_types=1);

namespace Fixtures\NoKernelForPureFunction;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SlugTest extends KernelTestCase
{
    public function testSlugifies(): void
    {
        $slug = strtolower('Hello');

        self::assertSame('hello', $slug);
    }
}

final class ServiceTest extends KernelTestCase
{
    public function testReadsAService(): void
    {
        self::bootKernel();

        self::assertTrue(true);
    }
}

final class PageTest extends WebTestCase
{
    public function testRendersThePage(): void
    {
        $client = static::createClient();

        self::assertNotNull($client);
    }
}
