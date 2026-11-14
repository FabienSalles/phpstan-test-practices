<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Collectors\TestCaseCollector;
use FabienSalles\PHPStanTestPractices\Collectors\TestDoubleCollector;
use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @phpstan-type TestDouble array{dependency: string, file: string, line: int}
 * @phpstan-type Method array{class: string, method: string, test: bool, fromTrait: bool, doubles: list<TestDouble>, calls: list<string>}
 * @implements Rule<CollectedDataNode>
 */
final readonly class PreferInMemoryDoubleRule implements Rule
{
    public const LEVEL = 3;

    public function __construct(
        private int $threshold,
        private string $severity,
        private int|string $level,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!Level::enables($this->level, self::LEVEL)) {
            return [];
        }

        $declared = [];
        foreach ($node->get(TestDoubleCollector::class) as $methods) {
            foreach ($methods as $method) {
                $existing = $declared[$method['class']][strtolower($method['method'])] ?? null;
                if ($existing === null || ($existing['fromTrait'] && !$method['fromTrait'])) {
                    $declared[$method['class']][strtolower($method['method'])] = $method;
                }
            }
        }

        $errors = [];
        foreach ($node->get(TestCaseCollector::class) as $file => $testCases) {
            foreach ($testCases as $testCase) {
                $methods = [];
                foreach ($testCase['lineage'] as $class) {
                    $methods += $declared[$class] ?? [];
                }

                $usages = [];
                foreach ($methods as $method) {
                    if (!$method['test']) {
                        continue;
                    }

                    foreach (self::doublesReachedFrom($method, $methods) as $double) {
                        $usages[$double['dependency']][$method['method']] ??= $double;
                    }
                }

                foreach ($usages as $dependency => $doubles) {
                    if (count($doubles) < $this->threshold) {
                        continue;
                    }

                    $linesInFile = array_map(static fn (array $double): int => $double['line'], array_filter($doubles, static fn (array $double): bool => $double['file'] === $file));
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        '%s is doubled in %d test methods (%s). Prefer a hand-written in-memory stub or fake, reused across the tests.',
                        $dependency,
                        count($doubles),
                        implode('(), ', array_keys($doubles)) . '()',
                    ))
                        ->identifier('testPractices.preferInMemoryDouble')
                        ->file($file)
                        ->line($linesInFile === [] ? $testCase['line'] : min($linesInFile))
                        ->tip('An in-memory implementation of the interface keeps each test short and survives changes to how the dependency is called.')
                        ->metadata(Severity::metadata($this->severity))
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * @param Method $method
     * @param array<string, Method> $methods
     * @param array<string, true> $visited
     * @return list<TestDouble>
     */
    private static function doublesReachedFrom(array $method, array $methods, array &$visited = []): array
    {
        $visited[strtolower($method['method'])] = true;
        $doubles = $method['doubles'];
        foreach ($method['calls'] as $call) {
            if (isset($methods[$call]) && !isset($visited[$call])) {
                $doubles = [...$doubles, ...self::doublesReachedFrom($methods[$call], $methods, $visited)];
            }
        }

        return $doubles;
    }
}
