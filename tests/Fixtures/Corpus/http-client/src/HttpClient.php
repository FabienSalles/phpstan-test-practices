<?php

declare(strict_types=1);

namespace Example\HttpClient;

interface HttpClient
{
    public function get(string $url): string;
}
