<?php

declare(strict_types=1);

namespace Fixtures\NoTestCaseConstructor;

use PHPUnit\Framework\TestCase;

final class NoTestCaseConstructorTest extends TestCase
{
    private int $total;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->total = 3;
    }

    public function testUsesTheConstructor(): void
    {
        self::assertSame(3, $this->total);
    }
}

final class Factory
{
    public function __construct()
    {
    }
}
