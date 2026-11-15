<?php

declare(strict_types=1);

namespace Fixtures\DontMockWhatYouDontOwn;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

interface OrderRepository
{
    public function save(): void;
}

final class DontMockWhatYouDontOwnTest extends TestCase
{
    use ProphecyTrait;

    public function testDoublesThirdPartyTypes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $concrete = $this->prophesize(EntityManager::class);
        $repository = $this->createStub(OrderRepository::class);

        $repository->save();

        self::assertSame([$entityManager, $concrete], [$entityManager, $concrete]);
    }
}
