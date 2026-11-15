<?php

declare(strict_types=1);

namespace Fixtures\Fix\BuiltInWebAssertions;

use PHPUnit\Framework\TestCase;

final class Response
{
    public function getStatusCode(): int
    {
        return 201;
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

final class StatusTest extends WebTestCaseLike
{
    public function testCreates(): void
    {
        $client = new Client();

        self::assertSame(201, $client->getResponse()->getStatusCode());
    }
}
