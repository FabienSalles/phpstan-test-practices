<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Collectors\TestCaseHierarchy;
use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
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

        $errors = [];
        foreach (TestCaseHierarchy::fromCollectedData($node)->concreteTestCases() as ['file' => $file, 'testCase' => $testCase, 'methods' => $methods]) {
            $usages = [];
            foreach ($methods as $method) {
                if (!$method['test']) {
                    continue;
                }

                foreach (TestCaseHierarchy::reachableFrom($method, $methods) as $reached) {
                    foreach ($reached['doubles'] as $double) {
                        $usages[$double['dependency']][$method['method']] ??= $double;
                    }
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

        return $errors;
    }
}
