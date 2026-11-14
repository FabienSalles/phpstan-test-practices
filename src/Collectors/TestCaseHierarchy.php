<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Collectors;

use PHPStan\Node\CollectedDataNode;

/**
 * @phpstan-import-type Method from TestCaseMethodCollector
 * @phpstan-import-type TestCase from TestCaseCollector
 */
final readonly class TestCaseHierarchy
{
    /**
     * @param list<array{file: string, testCase: TestCase, methods: array<string, Method>}> $testCases
     */
    private function __construct(
        private array $testCases,
    ) {
    }

    public static function fromCollectedData(CollectedDataNode $node): self
    {
        $declared = [];
        foreach ($node->get(TestCaseMethodCollector::class) as $methods) {
            foreach ($methods as $method) {
                $existing = $declared[$method['class']][strtolower($method['method'])] ?? null;
                if ($existing === null || ($existing['fromTrait'] && !$method['fromTrait'])) {
                    $declared[$method['class']][strtolower($method['method'])] = $method;
                }
            }
        }

        $testCases = [];
        foreach ($node->get(TestCaseCollector::class) as $file => $collected) {
            foreach ($collected as $testCase) {
                $methods = [];
                foreach ($testCase['lineage'] as $class) {
                    $methods += $declared[$class] ?? [];
                }

                $testCases[] = ['file' => $file, 'testCase' => $testCase, 'methods' => $methods];
            }
        }

        return new self($testCases);
    }

    /**
     * @return list<array{file: string, testCase: TestCase, methods: array<string, Method>}>
     */
    public function concreteTestCases(): array
    {
        return $this->testCases;
    }

    /**
     * @param Method $method
     * @param array<string, Method> $methods
     * @param array<string, Method> $reached
     * @return array<string, Method>
     */
    public static function reachableFrom(array $method, array $methods, array $reached = []): array
    {
        $reached[strtolower($method['method'])] = $method;
        foreach ($method['calls'] as $call) {
            if (isset($methods[$call]) && !isset($reached[$call])) {
                $reached = self::reachableFrom($methods[$call], $methods, $reached);
            }
        }

        return $reached;
    }
}
