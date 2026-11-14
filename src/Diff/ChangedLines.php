<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Diff;

use PHPStan\Analyser\Error;
use RuntimeException;

final readonly class ChangedLines
{
    private const WHOLE_FILE_IDENTIFIERS = ['testPractices.preferInMemoryDouble'];

    /**
     * @param array<string, array<int, true>> $lines
     */
    private function __construct(
        private array $lines,
    ) {
    }

    public static function since(string $base, string $workingDirectory): self
    {
        $root = self::git($workingDirectory, 'rev-parse', '--show-toplevel')[0] ?? '';
        $diff = self::git($workingDirectory, '-c', 'core.quotePath=false', 'diff', '--no-color', '--no-ext-diff', '--unified=0', $base, '--');

        return self::fromUnifiedDiff($diff, $root);
    }

    /**
     * @param list<string> $diff
     */
    public static function fromUnifiedDiff(array $diff, string $root): self
    {
        $lines = [];
        $file = null;
        foreach ($diff as $line) {
            if (str_starts_with($line, '+++ ')) {
                $file = str_starts_with($line, '+++ b/') ? $root . '/' . substr($line, 6) : null;
                if ($file !== null) {
                    $lines[$file] ??= [];
                }

                continue;
            }

            if ($file === null || preg_match('/^@@ -\S+ \+(\d+)(?:,(\d+))? @@/', $line, $hunk) !== 1) {
                continue;
            }

            $start = (int) $hunk[1];
            $count = isset($hunk[2]) ? (int) $hunk[2] : 1;
            for ($number = $start; $number < $start + $count; $number++) {
                $lines[$file][$number] = true;
            }
        }

        return new self($lines);
    }

    public function contains(Error $error): bool
    {
        $file = $error->getFilePath();
        if (!isset($this->lines[$file])) {
            return false;
        }

        $line = $error->getLine();

        return $line === null || in_array($error->getIdentifier(), self::WHOLE_FILE_IDENTIFIERS, true) || isset($this->lines[$file][$line]);
    }

    /**
     * @return list<string>
     */
    private static function git(string $workingDirectory, string ...$arguments): array
    {
        $command = sprintf('git -C %s %s 2>&1', escapeshellarg($workingDirectory), implode(' ', array_map(escapeshellarg(...), $arguments)));
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf('"%s" failed: %s', $command, implode("\n", $output)));
        }

        return $output;
    }
}
