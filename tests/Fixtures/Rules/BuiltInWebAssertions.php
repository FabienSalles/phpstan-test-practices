<?php

declare(strict_types=1);

namespace Fixtures\BuiltInWebAssertions;

use PHPUnit\Framework\TestCase;

final class Response
{
    public function getStatusCode(): int
    {
        return 200;
    }
}

final class Crawler
{
    public function filter(string $selector): self
    {
        return $this;
    }

    public function first(): self
    {
        return $this;
    }

    public function text(): string
    {
        return 'Saved';
    }
}

final class Client
{
    public function getResponse(): Response
    {
        return new Response();
    }
}

abstract class WebTestCaseLike extends TestCase
{
    public static function assertResponseStatusCodeSame(int $expectedCode): void
    {
    }
}

final class BuiltInWebAssertionsTest extends WebTestCaseLike
{
    public function testReadsTheResponseByHand(): void
    {
        $client = new Client();
        $crawler = new Crawler();

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame('Saved', trim($crawler->filter('.alert')->text()));
        self::assertStringContainsString('Sav', $crawler->filter('.alert')->text());
        self::assertCount(1, $crawler->filter('.alert'));
        self::assertSame('Saved', $crawler->filter('li')->first()->filter('span')->text());
    }
}
