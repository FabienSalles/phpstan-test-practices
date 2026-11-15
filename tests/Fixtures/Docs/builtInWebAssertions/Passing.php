<?php

declare(strict_types=1);

namespace Docs\BuiltInWebAssertions\Passing;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderControllerTest extends WebTestCase
{
    public function testCreatesAnOrder(): void
    {
        $client = static::createClient();

        $client->request('POST', '/orders');

        self::assertResponseStatusCodeSame(201);
    }
}
