<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\PhpUnit;

use DOMDocument;
use DOMElement;

final readonly class TestSuites
{
    private const CANDIDATES = ['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'];

    /**
     * @param list<array{directories: list<array{path: string, prefix: string, suffix: string}>, files: list<string>, excludes: list<string>}> $suites
     */
    private function __construct(
        public ?string $configuration,
        private array $suites,
    ) {
    }

    public static function load(?string $configuration, string $workingDirectory): self
    {
        $configuration ??= self::detect($workingDirectory);
        if ($configuration === null) {
            return new self(null, []);
        }

        $path = str_starts_with($configuration, '/') ? $configuration : $workingDirectory . '/' . $configuration;
        $document = new DOMDocument();
        if (!is_file($path) || !@$document->load($path)) {
            return new self(null, []);
        }

        $base = dirname((string) realpath($path));
        $suites = [];
        foreach ($document->getElementsByTagName('testsuite') as $suite) {
            $suites[] = [
                'directories' => array_map(static fn (DOMElement $directory): array => [
                    'path' => self::absolute($base, $directory->textContent),
                    'prefix' => $directory->getAttribute('prefix'),
                    'suffix' => $directory->hasAttribute('suffix') ? $directory->getAttribute('suffix') : 'Test.php',
                ], self::children($suite, 'directory')),
                'files' => array_map(static fn (DOMElement $file): string => self::absolute($base, $file->textContent), self::children($suite, 'file')),
                'excludes' => array_map(static fn (DOMElement $exclude): string => self::absolute($base, $exclude->textContent), self::children($suite, 'exclude')),
            ];
        }

        return new self($path, $suites);
    }

    public function includes(string $file): bool
    {
        $file = (string) (realpath($file) ?: $file);
        foreach ($this->suites as $suite) {
            if (self::isExcluded($file, $suite['excludes'])) {
                continue;
            }

            if (in_array($file, $suite['files'], true)) {
                return true;
            }

            foreach ($suite['directories'] as $directory) {
                if (self::isUnder($file, $directory['path']) && str_starts_with(basename($file), $directory['prefix']) && str_ends_with($file, $directory['suffix'])) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function detect(string $workingDirectory): ?string
    {
        foreach (self::CANDIDATES as $candidate) {
            if (is_file($workingDirectory . '/' . $candidate)) {
                return $workingDirectory . '/' . $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<DOMElement>
     */
    private static function children(DOMElement $parent, string $name): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private static function absolute(string $base, string $path): string
    {
        $path = trim($path);
        $absolute = str_starts_with($path, '/') ? $path : $base . '/' . $path;

        return rtrim((string) (realpath($absolute) ?: $absolute), '/');
    }

    /**
     * @param list<string> $excludes
     */
    private static function isExcluded(string $file, array $excludes): bool
    {
        foreach ($excludes as $exclude) {
            if ($file === $exclude || self::isUnder($file, $exclude)) {
                return true;
            }
        }

        return false;
    }

    private static function isUnder(string $file, string $directory): bool
    {
        if (str_contains($directory, '*')) {
            return fnmatch($directory . '/*', $file);
        }

        return str_starts_with($file, $directory . '/');
    }
}
