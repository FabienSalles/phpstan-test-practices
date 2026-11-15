<?php

declare(strict_types=1);

namespace Fixtures\PermissiveStub;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

interface Repository
{
    public function get(string $id): ?string;

    public function save(string $value): void;
}

final class PermissiveStubTest extends TestCase
{
    use ProphecyTrait;

    public function testStubsStrictly(): void
    {
        $repository = $this->prophesize(Repository::class);
        $repository->get(Argument::type('string'))->willReturn('a');
        $phpunit = $this->createStub(Repository::class);
        $phpunit->method('get')->with('a')->willReturn('a');

        self::assertSame(['a', 'a'], [$repository->reveal()->get('x'), $phpunit->get('a')]);
    }

    public function testStubsPermissivelyOrDispatches(): void
    {
        $repository = $this->prophesize(Repository::class);
        $repository->get(Argument::any())->willReturn('a');
        $dispatch = $this->prophesize(Repository::class);
        $dispatch->get('1')->willReturn('one');
        $dispatch->get('2')->willReturn('two');
        $mock = $this->createMock(Repository::class);
        $mock->expects(self::once())->method('get')->with('a')->willReturn('a');

        self::assertSame(['a', 'one', 'two', 'a'], [$repository->reveal()->get('x'), $dispatch->reveal()->get('1'), $dispatch->reveal()->get('2'), $mock->get('a')]);
    }

    public function testVerifiesTheSameCallLater(): void
    {
        $repository = $this->prophesize(Repository::class);
        $repository->save('a')->willReturn(null);

        $repository->reveal()->save('a');

        $repository->save('a')->shouldHaveBeenCalled();
    }
}
