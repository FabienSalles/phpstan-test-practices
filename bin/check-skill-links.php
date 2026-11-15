<?php

declare(strict_types=1);

use FabienSalles\PHPStanTestPractices\Tools\SkillLinks\SkillLinkChecker;

require __DIR__ . '/../vendor/autoload.php';

$checker = new SkillLinkChecker(static function (string $url): ?string {
    $body = @file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 20]]));
    $status = $http_response_header[0] ?? '';

    return $body !== false && preg_match('~^HTTP/\S+ 200~', $status) === 1 ? $body : null;
});

$problems = $checker->check(dirname(__DIR__));
foreach ($problems as $problem) {
    fwrite(STDERR, $problem . PHP_EOL);
}

if ($problems !== []) {
    exit(1);
}

echo 'Every rule is mapped and every skill link resolves.' . PHP_EOL;
