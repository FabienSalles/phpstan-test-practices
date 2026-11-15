<?php

declare(strict_types=1);

namespace Docs\DontMockWhatYouDontOwn\Failing;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final readonly class OrderArchiver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function archive(): void
    {
        $this->entityManager->flush();
    }
}

final class OrderArchiverTest extends TestCase
{
    public function testFlushesTheArchive(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('flush');

        (new OrderArchiver($entityManager))->archive();

        self::assertTrue(true);
    }
}
