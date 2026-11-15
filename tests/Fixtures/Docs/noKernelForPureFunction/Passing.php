<?php

declare(strict_types=1);

namespace Docs\NoKernelForPureFunction\Passing;

use PHPUnit\Framework\TestCase;

final class Slugger
{
    public function slug(string $title): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    }
}

final class SluggerTest extends TestCase
{
    public function testSlugsATitle(): void
    {
        $slug = (new Slugger())->slug('Hello World');

        self::assertSame('hello-world', $slug);
    }
}
