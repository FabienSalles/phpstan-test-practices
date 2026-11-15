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
final readonly class TestMethodIsDiscoverableRule implements Rule
{
    public const LEVEL = 0;

    public function __construct(
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

        $candidates = [];
        $reached = [];
        foreach (TestCaseHierarchy::fromCollectedData($node)->concreteTestCases() as ['methods' => $methods]) {
            foreach ($methods as $method) {
                if ($method['asserts'] && !$method['test']) {
                    $candidates[$method['file'] . ':' . $method['line']] = $method;
                }

                if (!$method['test'] && !$method['root']) {
                    continue;
                }

                foreach (TestCaseHierarchy::reachableFrom($method, $methods) as $reachable) {
                    $reached[$reachable['file'] . ':' . $reachable['line']] = true;
                }
            }
        }

        $errors = [];
        foreach (array_diff_key($candidates, $reached) as $method) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Method %s() makes assertions but PHPUnit never runs it: it is not a test (test prefix, #[Test] or @test) and no test calls it.',
                $method['method'],
            ))
                ->identifier('testPractices.testMethodIsDiscoverable')
                ->file($method['file'])
                ->line($method['line'])
                ->tip($method['public'] ? 'Add #[Test] or the test prefix, or call it from a test.' : 'Make it a public test method, or call it from a test.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }
}
