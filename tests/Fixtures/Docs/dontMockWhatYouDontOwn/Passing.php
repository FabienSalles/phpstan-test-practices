<?php

declare(strict_types=1);

namespace Docs\DontMockWhatYouDontOwn\Passing;

use PHPUnit\Framework\TestCase;

interface Archive
{
    public function store(string $order): void;
}

final class InMemoryArchive implements Archive
{
    /** @var list<string> */
    public array $orders = [];

    public function store(string $order): void
    {
        $this->orders[] = $order;
    }
}

final readonly class OrderArchiver
{
    public function __construct(private Archive $archive)
    {
    }

    public function archive(string $order): void
    {
        $this->archive->store($order);
    }
}

final class OrderArchiverTest extends TestCase
{
    public function testStoresTheOrder(): void
    {
        $archive = new InMemoryArchive();

        (new OrderArchiver($archive))->archive('order-1');

        self::assertSame(['order-1'], $archive->orders);
    }
}
